<?php
/**
 * Admin pages logic: dashboard numbers, customer / store / rider lists and details,
 * and approve / reject / suspend / reactivate for stores and riders.
 */

function admin_iso($dt)
{
    return $dt ? date('c', strtotime($dt)) : null;
}

/** Orders in a list/detail, newest first. $where is a fixed SQL fragment (never user input). */
function admin_order_rows($where, array $params, $limit = null)
{
    $sql = "SELECT o.id, o.status, o.total_price, o.created_at, b.name AS bag_name,
                   c.full_name AS customer_name, s.store_name
            FROM orders o
            JOIN bag_types b ON b.id = o.bag_type_id
            JOIN customers c ON c.user_id = o.customer_id
            JOIN stores s ON s.user_id = o.store_id
            WHERE $where
            ORDER BY o.created_at DESC, o.id DESC" . ($limit ? ' LIMIT ' . (int) $limit : '');
    $st = db()->prepare($sql);
    $st->execute($params);
    $out = [];
    foreach ($st as $r) {
        $out[] = [
            'id'           => (int) $r['id'],
            'status'       => $r['status'],
            'total'        => (float) $r['total_price'],
            'bag'          => $r['bag_name'],
            'customerName' => $r['customer_name'],
            'storeName'    => $r['store_name'],
            'createdAt'    => admin_iso($r['created_at']),
        ];
    }
    return $out;
}

/* ---------------------------------------------------------------- dashboard */

function admin_dashboard()
{
    $pdo = db();
    $one = function ($sql) use ($pdo) { return $pdo->query($sql)->fetchColumn(); };

    $stats = [
        'totalCustomers'  => (int) $one("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
        'activeStores'    => (int) $one("SELECT COUNT(*) FROM users WHERE role = 'store' AND status = 'active'"),
        'activeRiders'    => (int) $one("SELECT COUNT(*) FROM users WHERE role = 'rider' AND status = 'active'"),
        'pendingStores'   => (int) $one("SELECT COUNT(*) FROM users WHERE role = 'store' AND status = 'pending_approval'"),
        'pendingRiders'   => (int) $one("SELECT COUNT(*) FROM users WHERE role = 'rider' AND status = 'pending_approval'"),
        'totalOrders'     => (int) $one('SELECT COUNT(*) FROM orders'),
        'inProgress'      => (int) $one("SELECT COUNT(*) FROM orders WHERE status NOT IN ('completed','cancelled','rejected')"),
        'completedOrders' => (int) $one("SELECT COUNT(*) FROM orders WHERE status = 'completed'"),
        'revenue'         => (float) $one("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status = 'completed'"),
    ];

    $counts = [];
    foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $r) {
        $counts[$r['status']] = (int) $r['n'];
    }

    $approvals = [];
    $st = $pdo->query(
        "SELECT u.id, u.role, u.created_at, COALESCE(s.store_name, r.full_name) AS name
         FROM users u
         LEFT JOIN stores s ON s.user_id = u.id
         LEFT JOIN riders r ON r.user_id = u.id
         WHERE u.role IN ('rider','store') AND u.status = 'pending_approval'
         ORDER BY u.created_at ASC, u.id ASC"
    );
    foreach ($st as $r) {
        $approvals[] = [
            'id' => (int) $r['id'], 'name' => $r['name'], 'type' => $r['role'] === 'store' ? 'Store' : 'Rider',
            'joined' => admin_iso($r['created_at']),
        ];
    }

    return ['stats' => $stats, 'statusCounts' => $counts, 'approvals' => $approvals,
            'recent' => admin_order_rows('1 = 1', [], 8)];
}

/* ---------------------------------------------------------------- customers */

function admin_customers()
{
    $st = db()->query(
        "SELECT u.id, u.email, u.created_at, c.full_name, c.contact_no, c.address,
                (SELECT COUNT(*) FROM orders o WHERE o.customer_id = u.id) AS order_count,
                (SELECT COALESCE(SUM(o.total_price), 0) FROM orders o
                  WHERE o.customer_id = u.id AND o.status = 'completed') AS spent
         FROM users u JOIN customers c ON c.user_id = u.id
         WHERE u.role = 'customer'
         ORDER BY u.created_at DESC, u.id DESC"
    );
    $list = [];
    foreach ($st as $r) {
        $list[] = [
            'id' => (int) $r['id'], 'name' => $r['full_name'], 'email' => $r['email'],
            'contact' => $r['contact_no'], 'address' => $r['address'],
            'orders' => (int) $r['order_count'], 'spent' => (float) $r['spent'],
            'joined' => admin_iso($r['created_at']),
        ];
    }
    $pdo = db();
    return [
        'customers' => $list,
        'stats' => [
            'totalOrders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'lifetime'    => (float) $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status = 'completed'")->fetchColumn(),
        ],
    ];
}

function admin_customer_detail($id)
{
    foreach (admin_customers()['customers'] as $c) {
        if ($c['id'] === (int) $id) {
            $orders = admin_order_rows('o.customer_id = ?', [(int) $id]);
            $done = array_filter($orders, function ($o) { return $o['status'] === 'completed'; });
            $c['orderList'] = $orders;
            $c['completedCount'] = count($done);
            $c['firstOrder'] = $orders ? $orders[count($orders) - 1]['createdAt'] : null;
            return $c;
        }
    }
    throw new UserError('Customer not found.');
}

/* ------------------------------------------------------------------- stores */

function admin_stores()
{
    $st = db()->query(
        "SELECT u.id, u.email, u.status, u.status_note, u.created_at,
                s.store_name, s.contact_no, s.address, s.description, s.latitude, s.longitude,
                s.profile_image, s.banner_image, s.is_featured, s.accepting_orders
         FROM users u JOIN stores s ON s.user_id = u.id
         WHERE u.role = 'store'
         ORDER BY u.created_at DESC, u.id DESC"
    );
    $prices = [];
    foreach (db()->query('SELECT sp.store_id, b.code, sp.price FROM store_prices sp JOIN bag_types b ON b.id = sp.bag_type_id') as $r) {
        $prices[(int) $r['store_id']][$r['code']] = (float) $r['price'];
    }
    $benefits = [];
    foreach (db()->query('SELECT sb.store_id, bf.code, bf.name FROM store_benefits sb JOIN benefits bf ON bf.id = sb.benefit_id ORDER BY bf.id') as $r) {
        $benefits[(int) $r['store_id']][] = ['code' => $r['code'], 'name' => $r['name']];
    }
    $out = [];
    foreach ($st as $r) {
        $id = (int) $r['id'];
        $p = isset($prices[$id]) ? $prices[$id] : null;
        $b = isset($benefits[$id]) ? $benefits[$id] : [];
        $out[] = [
            'id' => $id, 'name' => $r['store_name'], 'email' => $r['email'], 'status' => $r['status'],
            'statusNote' => $r['status_note'], 'joined' => admin_iso($r['created_at']),
            'contact' => $r['contact_no'], 'address' => $r['address'], 'description' => $r['description'],
            'latitude' => $r['latitude'] === null ? null : (float) $r['latitude'],
            'longitude' => $r['longitude'] === null ? null : (float) $r['longitude'],
            'image' => $r['profile_image'], 'banner' => $r['banner_image'],
            'featured' => (bool) $r['is_featured'], 'accepting' => (bool) $r['accepting_orders'],
            'rating' => null,                                   // the schema has no ratings (yet)
            'prices' => ($p && count($p) === 3) ? $p : null,
            'benefits' => array_map(function ($x) { return $x['name']; }, $b),
            'freeDelivery' => count(array_filter($b, function ($x) { return $x['code'] === 'FREE_DELIVERY'; })) > 0,
        ];
    }
    return $out;
}

function admin_store_detail($id)
{
    foreach (admin_stores() as $s) {
        if ($s['id'] === (int) $id) {
            $st = db()->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(total_price), 0) AS v FROM orders WHERE store_id = ? AND status = 'completed'");
            $st->execute([(int) $id]);
            $t = $st->fetch();
            $s['orderList'] = admin_order_rows('o.store_id = ?', [(int) $id], 8);
            $s['completedCount'] = (int) $t['n'];
            $s['lifetime'] = (float) $t['v'];
            return $s;
        }
    }
    throw new UserError('Store not found.');
}

/* ------------------------------------------------------------------- riders */

// orders a rider took part in: accepted a job, or appears in the status history
const ADMIN_RIDER_ORDER_FILTER =
    'EXISTS (SELECT 1 FROM delivery_jobs j WHERE j.order_id = o.id AND j.rider_id = ?)
     OR EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id = o.id AND h.changed_by = ?)';

function admin_riders()
{
    $st = db()->query(
        "SELECT u.id, u.email, u.status, u.status_note, u.created_at, r.full_name, r.contact_no,
                (SELECT COUNT(*) FROM orders o WHERE
                    EXISTS (SELECT 1 FROM delivery_jobs j WHERE j.order_id = o.id AND j.rider_id = u.id)
                 OR EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id = o.id AND h.changed_by = u.id)) AS jobs
         FROM users u JOIN riders r ON r.user_id = u.id
         WHERE u.role = 'rider'
         ORDER BY u.created_at DESC, u.id DESC"
    );
    $out = [];
    foreach ($st as $r) {
        $out[] = [
            'id' => (int) $r['id'], 'name' => $r['full_name'], 'email' => $r['email'], 'contact' => $r['contact_no'],
            'status' => $r['status'], 'statusNote' => $r['status_note'], 'joined' => admin_iso($r['created_at']),
            'jobs' => (int) $r['jobs'],
        ];
    }
    return $out;
}

function admin_rider_detail($id)
{
    foreach (admin_riders() as $r) {
        if ($r['id'] === (int) $id) {
            $orders = admin_order_rows(ADMIN_RIDER_ORDER_FILTER, [(int) $id, (int) $id]);
            $last = [];                                         // newest history note written by this rider, per order
            $st = db()->prepare('SELECT order_id, note, created_at FROM order_status_history WHERE changed_by = ? ORDER BY created_at ASC, id ASC');
            $st->execute([(int) $id]);
            foreach ($st as $h) {
                $last[(int) $h['order_id']] = ['note' => $h['note'], 'at' => admin_iso($h['created_at'])];
            }
            foreach ($orders as &$o) {
                $o['lastNote'] = isset($last[$o['id']]) ? $last[$o['id']]['note'] : null;
                $o['lastAt']   = isset($last[$o['id']]) ? $last[$o['id']]['at'] : $o['createdAt'];
            }
            unset($o);
            $r['activity'] = $orders;
            return $r;
        }
    }
    throw new UserError('Rider not found.');
}

/* ------------------------------------------------- approve / reject / suspend */

/**
 * Change a store's or rider's account status. $action: approve | reject | suspend
 *   approve  : pending_approval | rejected | suspended -> active   (also "reactivate")
 *   reject   : pending_approval -> rejected (reason saved, shown to the applicant)
 *   suspend  : active -> suspended
 * Row-locked, so a stale page (someone else already changed it) gets a clear message instead of a bad change.
 */
function admin_set_user_status($adminId, $role, $userId, $action, $reason)
{
    $rules = [
        'approve' => [['pending_approval', 'rejected', 'suspended'], 'active'],
        'reject'  => [['pending_approval'], 'rejected'],
        'suspend' => [['active'], 'suspended'],
    ];
    if (!isset($rules[$action]) || !in_array($role, ['store', 'rider'], true)) {
        throw new UserError('Unknown action.');
    }
    list($from, $to) = $rules[$action];
    $reason = trim((string) $reason);
    if (strlen($reason) > 255) {
        throw new UserError('The reason is too long (255 characters at most).');
    }
    $note = $action === 'reject' ? ($reason !== '' ? $reason : 'Rejected by admin') : null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT status FROM users WHERE id = ? AND role = ?' . db_lock_clause());
        $st->execute([(int) $userId, $role]);
        $cur = $st->fetchColumn();
        if ($cur === false) {
            throw new UserError(ucfirst($role) . ' not found.');
        }
        if (!in_array($cur, $from, true)) {
            throw new UserError('This ' . $role . "'s status has changed. Refresh the list and try again.");
        }
        $pdo->prepare('UPDATE users SET status = ?, status_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?')
            ->execute([$to, $note, (int) $adminId, now(), (int) $userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Feature / unfeature a store (stores.is_featured; shown on the Customer Home page).
 * Only an active store can be featured; unfeaturing is always allowed.
 */
function admin_set_store_featured($storeId, $featured)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'store'" . db_lock_clause());
        $st->execute([(int) $storeId]);
        $status = $st->fetchColumn();
        if ($status === false) {
            throw new UserError('Store not found.');
        }
        if ($featured && $status !== 'active') {
            throw new UserError('Only active stores can be featured. Refresh the list and try again.');
        }
        $pdo->prepare('UPDATE stores SET is_featured = ? WHERE user_id = ?')->execute([$featured ? 1 : 0, (int) $storeId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
