<?php
/**
 * Rider logic: the job board, accepting jobs, the rider's own jobs / history / earnings, profile.
 * A "job" is one trip. Every order has up to two: 'send' (customer -> store) and 'return' (store -> customer).
 * The store code creates the jobs; this file lets riders take them.
 */
require_once __DIR__ . '/../store/store_orders.php';   // for store_order_set_status()

function rider_load($id)
{
    $st = db()->prepare("SELECT u.id, u.email, u.status, r.full_name, r.contact_no FROM users u JOIN riders r ON r.user_id = u.id WHERE u.id = ? AND u.role = 'rider'");
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ? ['id' => (int) $r['id'], 'email' => $r['email'], 'status' => $r['status'], 'name' => $r['full_name'], 'contact' => $r['contact_no']] : null;
}

function rider_update_profile($id, array $in)
{
    $name    = trim(isset($in['name']) ? (string) $in['name'] : '');
    $contact = trim(isset($in['contact']) ? (string) $in['contact'] : '');
    if ($name === '' || mb_strlen($name) > 120) {
        throw new UserError('Enter your full name.');
    }
    if (!preg_match('/^[0-9+\-()\s]{7,20}$/', $contact)) {
        throw new UserError('Enter a valid contact number.');
    }
    db()->prepare('UPDATE riders SET full_name = ?, contact_no = ? WHERE user_id = ?')->execute([$name, $contact, $id]);
}

/**
 * Turn one database row into the array the page uses.
 * $showCustomer = false hides who the customer is.
 * $showContact  = true adds the customer's and the store's phone numbers (only for jobs the rider has accepted).
 */
function rider_job_row(array $r, $showCustomer, $showContact = false)
{
    $km = (float) $r['distance_km'];
    return [
        'id'          => (int) $r['id'],                              // the JOB id (not the order id)
        'orderId'     => (int) $r['order_id'],
        'type'        => $r['job_type'],                              // 'send' | 'return'
        'shop'        => $r['store_name'],
        'pickup'      => $r['pickup_address'],
        'dropoff'     => $r['dropoff_address'],
        'distanceKm'  => $km,
        'pay'         => (float) $r['rider_pay'],
        'minutes'     => max(5, (int) round($km * 5)),                // rough estimate: 5 minutes per km
        'customer'    => $showCustomer ? $r['full_name'] : null,
        'customerContact' => ($showCustomer && $showContact) ? $r['contact_no'] : null,
        'shopContact'     => $showContact ? $r['store_contact'] : null,
        'orderStatus' => $r['order_status'],
        'acceptedAt'  => $r['accepted_at'] ? date('c', strtotime($r['accepted_at'])) : null,
        'completedAt' => $r['completed_at'] ? date('c', strtotime($r['completed_at'])) : null,
    ];
}

/** Shared SELECT: a job + its store, customer and order status. $where / $order are fixed strings from this file. */
function rider_jobs_query($where, array $params, $order)
{
    $st = db()->prepare(
        "SELECT j.*, s.store_name, s.contact_no AS store_contact, c.full_name, c.contact_no, o.status AS order_status
         FROM delivery_jobs j
         JOIN orders o ON o.id = j.order_id
         JOIN stores s ON s.user_id = o.store_id
         JOIN customers c ON c.user_id = o.customer_id
         WHERE $where ORDER BY $order"
    );
    $st->execute($params);
    return $st->fetchAll();
}

/** Open jobs anyone approved can take. The customer's name stays hidden until a rider accepts. */
function rider_available_jobs()
{
    return array_map(function ($r) { return rider_job_row($r, false); },
        rider_jobs_query("j.status = 'available'", [], 'j.created_at'));
}

/** Jobs this rider accepted and has not finished. */
function rider_current_jobs($riderId)
{
    return array_map(function ($r) { return rider_job_row($r, true, true); },
        rider_jobs_query("j.status = 'accepted' AND j.rider_id = ?", [$riderId], 'j.accepted_at DESC'));
}

/** Finished jobs, newest first, one page at a time. (No phone numbers: the job is over.) */
function rider_history_jobs($riderId, $limit = 50, $offset = 0)
{
    $limit  = max(1, min(100, (int) $limit));      // (int) casts make these safe to put in the SQL text
    $offset = max(0, (int) $offset);
    return array_map(function ($r) { return rider_job_row($r, true, false); },
        rider_jobs_query("j.status = 'completed' AND j.rider_id = ?", [$riderId], "j.completed_at DESC, j.id DESC LIMIT $limit OFFSET $offset"));
}

function rider_stats($riderId)
{
    // "Today" is decided here in PHP (Asia/Manila, same clock that wrote completed_at), not with CURDATE(),
    // so it cannot drift if the database server runs on another time zone.
    $start = date('Y-m-d 00:00:00');
    $end   = date('Y-m-d 00:00:00', strtotime('+1 day'));
    $st = db()->prepare(
        "SELECT COUNT(*) AS done, COALESCE(SUM(rider_pay), 0) AS earned,
                COALESCE(SUM(CASE WHEN completed_at >= ? AND completed_at < ? THEN 1 ELSE 0 END), 0) AS today,
                COALESCE(SUM(CASE WHEN completed_at >= ? AND completed_at < ? THEN rider_pay ELSE 0 END), 0) AS earned_today
         FROM delivery_jobs WHERE rider_id = ? AND status = 'completed'"
    );
    $st->execute([$start, $end, $start, $end, $riderId]);
    $r = $st->fetch();
    return ['completed' => (int) $r['done'], 'earned' => (float) $r['earned'],
            'today' => (int) $r['today'], 'earnedToday' => (float) $r['earned_today']];
}

/** Everything the rider pages need in one array. */
function rider_board($riderId, $historyLimit = 50, $historyOffset = 0)
{
    return [
        'available' => rider_available_jobs(),
        'current'   => rider_current_jobs($riderId),
        'history'   => rider_history_jobs($riderId, $historyLimit, $historyOffset),
        'stats'     => rider_stats($riderId),
    ];
}

/**
 * Accept a job. Two riders can press Accept at the same instant; exactly ONE must win.
 * The transaction + row lock + "WHERE status = 'available'" make that true.
 */
function rider_accept_job($riderId, $jobId)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Lock the job row (and its order) until we commit, so nobody else can change them meanwhile.
        $st = $pdo->prepare('SELECT j.*, o.status AS order_status FROM delivery_jobs j JOIN orders o ON o.id = j.order_id WHERE j.id = ?' . db_lock_clause());
        $st->execute([$jobId]);
        $job = $st->fetch();
        if (!$job) {
            throw new UserError('Job not found.');
        }
        if ($job['status'] !== 'available') {
            throw new UserError('Another rider already took this job. The list has been refreshed.');
        }
        $isSend = $job['job_type'] === 'send';
        // The order must be in the matching waiting state (e.g. a cancelled order must not be accepted).
        if ($job['order_status'] !== ($isSend ? 'waiting_for_driver' : 'waiting_for_return_driver')) {
            throw new UserError('This order changed and the job is no longer available.');
        }

        // The atomic claim: only succeeds if the job is STILL 'available'.
        $claim = $pdo->prepare("UPDATE delivery_jobs SET rider_id = ?, status = 'accepted', accepted_at = ? WHERE id = ? AND status = 'available'");
        $claim->execute([$riderId, now(), $jobId]);
        if ($claim->rowCount() !== 1) {
            throw new UserError('Another rider already took this job. The list has been refreshed.');
        }

        // Move the ORDER forward (the hand-off table in src/README.md) and write the timeline row.
        store_order_set_status(
            $job['order_id'],
            $isSend ? 'enroute_to_store' : 'enroute_to_customer',
            $riderId,
            $isSend ? 'Rider accepted send job' : 'Rider accepted return job'
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
