<?php
/**
 * Customer logic: profile, browsing stores, placing / tracking / finishing orders.
 * "repo" = repository: the only place that talks to the database for customers.
 * Pages never run SQL; they call the API files, which call these functions.
 */
require_once __DIR__ . '/../store/store_orders.php';   // we reuse store_order_set_status() for the history trail

const CUSTOMER_BAG_CODES = ['small', 'medium', 'large'];

/** The customer's profile as an array (or null). Coordinates come back as floats or null. */
function customer_load($id)
{
    $st = db()->prepare(
        "SELECT u.id, u.email, u.created_at, c.full_name, c.contact_no, c.address, c.latitude, c.longitude
         FROM users u JOIN customers c ON c.user_id = u.id
         WHERE u.id = ? AND u.role = 'customer'"
    );
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    return [
        'id'        => (int) $r['id'],
        'email'     => $r['email'],
        'name'      => $r['full_name'],
        'contact'   => $r['contact_no'],
        'address'   => $r['address'],
        'latitude'  => $r['latitude'] === null ? null : (float) $r['latitude'],
        'longitude' => $r['longitude'] === null ? null : (float) $r['longitude'],
        'joined'    => date('c', strtotime($r['created_at'])),       // "Member since" on the profile page
    ];
}

/** Save name (optional), contact number, address and the map pin (the pin is what makes delivery fees possible). */
function customer_update_profile($id, array $in)
{
    $name    = isset($in['name']) ? trim((string) $in['name']) : null;     // null = the caller did not send a name
    $contact = trim(isset($in['contact']) ? (string) $in['contact'] : '');
    $address = trim(isset($in['address']) ? (string) $in['address'] : '');
    $lat     = isset($in['latitude']) ? $in['latitude'] : '';
    $lng     = isset($in['longitude']) ? $in['longitude'] : '';

    if (!preg_match('/^[0-9+\-()\s]{7,20}$/', $contact)) {
        throw new UserError('Enter a valid contact number.');
    }
    if (mb_strlen($address) < 5 || mb_strlen($address) > 500) {
        throw new UserError('Enter your pickup / delivery address.');
    }
    // Same Philippines-only bounds the store pages use.
    if (!is_numeric($lat) || !is_numeric($lng)
        || (float) $lat < 4 || (float) $lat > 22 || (float) $lng < 115 || (float) $lng > 128) {
        throw new UserError('Pin your address on the map (inside the Philippines) so delivery fees can be calculated.');
    }
    if ($name !== null && ($name === '' || mb_strlen($name) > 120)) {
        throw new UserError('Enter your full name.');
    }
    $pdo = db();
    $pdo->prepare('UPDATE customers SET contact_no = ?, address = ?, latitude = ?, longitude = ? WHERE user_id = ?')
        ->execute([$contact, $address, round((float) $lat, 7), round((float) $lng, 7), $id]);
    if ($name !== null) {
        $pdo->prepare('UPDATE customers SET full_name = ? WHERE user_id = ?')->execute([$name, $id]);
    }
}

/** Headline numbers for the profile / home page. */
function customer_stats($id)
{
    $st = db()->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'completed'), 0) AS completed,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_price END), 0) AS spent
         FROM orders WHERE customer_id = ?"
    );
    $st->execute([$id]);
    $r = $st->fetch();
    return ['total' => (int) $r['total'], 'completed' => (int) $r['completed'], 'spent' => (float) $r['spent']];
}

/**
 * Every approved store, with this customer's distance and delivery fee already worked out.
 * Uses the v_store_listing VIEW from lavandera.sql (a saved SELECT that joins stores + prices + benefits).
 */
function customer_stores_list($customerId)
{
    $me   = customer_load($customerId);
    $rows = db()->query('SELECT * FROM v_store_listing ORDER BY is_featured DESC, store_name')->fetchAll();
    $out  = [];
    foreach ($rows as $r) {
        if ($r['price_small'] === null || $r['price_medium'] === null || $r['price_large'] === null) {
            continue;                                   // a store without all 3 prices cannot take orders
        }
        $hasPins = $me && $me['latitude'] !== null && $r['latitude'] !== null;
        $km      = $hasPins ? distance_km($me['latitude'], $me['longitude'], (float) $r['latitude'], (float) $r['longitude']) : null;
        $free    = (bool) $r['has_free_delivery'];
        $out[] = [
            'id'           => (int) $r['store_id'],
            'name'         => $r['store_name'],
            'description'  => $r['description'],
            'address'      => $r['address'],
            'contact'      => $r['contact_no'],
            'image'        => $r['profile_image'],
            'banner'       => $r['banner_image'],
            'featured'     => (bool) $r['is_featured'],
            'accepting'    => (bool) $r['accepting_orders'],
            'freeDelivery' => $free,
            'prices'       => ['small' => (float) $r['price_small'], 'medium' => (float) $r['price_medium'], 'large' => (float) $r['price_large']],
            'distanceKm'   => $km,                                          // null until the customer pins their address
            'deliveryFee'  => $km === null ? null : ($free ? 0 : calc_delivery_fee($km)),   // ONE leg; the order has two
        ];
    }
    return $out;
}

/**
 * Place an order. The SERVER recalculates every price and fee itself, so a customer can never
 * send "total: 1 peso" from the browser. $in = ['store_id', 'bag', 'payment', 'notes'].
 * Returns the new order id.
 */
function customer_place_order($customerId, array $in)
{
    $storeId = (int) (isset($in['store_id']) ? $in['store_id'] : 0);
    $bagCode = isset($in['bag']) ? (string) $in['bag'] : '';
    $payment = isset($in['payment']) ? (string) $in['payment'] : '';
    $notes   = mb_substr(trim(isset($in['notes']) ? (string) $in['notes'] : ''), 0, 500);

    if (!in_array($bagCode, CUSTOMER_BAG_CODES, true)) {
        throw new UserError('Choose a bag size.');
    }
    if ($payment !== 'cash') {   // cash is the only option for now; the payments column can take more later
        throw new UserError('Choose a payment method.');
    }
    $me = customer_load($customerId);
    if (!$me || $me['latitude'] === null || $me['longitude'] === null) {
        throw new UserError('Pin your address on the map in My Profile first, so we can work out the delivery fee.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // The store must be approved and accepting orders RIGHT NOW (it may have paused since the page loaded).
        $st = $pdo->prepare('SELECT * FROM v_store_listing WHERE store_id = ?');
        $st->execute([$storeId]);
        $store = $st->fetch();
        if (!$store || !(int) $store['accepting_orders']) {
            throw new UserError('This store is not accepting orders right now.');
        }
        if ($store['latitude'] === null) {
            throw new UserError('This store has not set its map location yet.');
        }

        // The store's price for the chosen bag.
        $st = $pdo->prepare('SELECT sp.price, b.id AS bag_id FROM store_prices sp JOIN bag_types b ON b.id = sp.bag_type_id WHERE sp.store_id = ? AND b.code = ?');
        $st->execute([$storeId, $bagCode]);
        $bag = $st->fetch();
        if (!$bag) {
            throw new UserError('This store does not offer that bag size.');
        }

        // Double-click / double-submit guard: the same bag from the same store in the last 15 seconds is one order.
        $dup = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND store_id = ? AND bag_type_id = ? AND status = 'pending_store' AND created_at >= ?");
        $dup->execute([$customerId, $storeId, (int) $bag['bag_id'], date('Y-m-d H:i:s', time() - 15)]);
        if ((int) $dup->fetchColumn() > 0) {
            throw new UserError('You just placed this order. Check My Orders.');
        }

        // Fees: one leg = calc_delivery_fee(km). Free-delivery stores charge the customer 0 for both legs.
        $km       = distance_km($me['latitude'], $me['longitude'], (float) $store['latitude'], (float) $store['longitude']);
        $free     = (bool) $store['has_free_delivery'];
        $leg      = $free ? 0 : calc_delivery_fee($km);
        $storeFee = (float) $bag['price'];
        $total    = round($storeFee + 2 * $leg, 2);       // must equal store_fee + send_fee + return_fee (the SQL CHECK)

        // Cash only: it is collected on delivery, so the order starts unpaid (the store marks it paid on receipt).
        $paymentStatus = 'unpaid';

        // Addresses, coordinates and fees are COPIED onto the order (snapshots) so later profile
        // or price changes never rewrite history.
        $pdo->prepare(
            'INSERT INTO orders (customer_id, store_id, bag_type_id, payment_method, payment_status,
                                 store_fee, send_fee, return_fee, total_price, free_delivery, distance_km,
                                 customer_address, customer_latitude, customer_longitude,
                                 store_address, store_latitude, store_longitude, notes, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $customerId, $storeId, (int) $bag['bag_id'], $payment, $paymentStatus,
            sprintf('%.2f', $storeFee), sprintf('%.2f', $leg), sprintf('%.2f', $leg), sprintf('%.2f', $total),
            $free ? 1 : 0, sprintf('%.2f', $km),
            $me['address'], $me['latitude'], $me['longitude'],
            (string) $store['address'], (float) $store['latitude'], (float) $store['longitude'],
            $notes === '' ? null : $notes, 'pending_store', now(),    // now() = same clock as the timeline rows
        ]);
        $orderId = (int) $pdo->lastInsertId();
        store_order_set_status($orderId, 'pending_store', $customerId, 'Order placed');   // first line of the timeline
        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** All of this customer's orders, newest first, each with its status timeline. */
function customer_orders_list($customerId)
{
    $st = db()->prepare(
        'SELECT o.*, s.store_name, b.code AS bag_code, b.name AS bag_name, b.weight_kg
         FROM orders o
         JOIN stores s ON s.user_id = o.store_id
         JOIN bag_types b ON b.id = o.bag_type_id
         WHERE o.customer_id = ?
         ORDER BY o.created_at DESC, o.id DESC'
    );
    $st->execute([$customerId]);
    $rows = $st->fetchAll();

    // One query for ALL timelines (instead of one query per order, which gets slow: the "N+1 problem").
    $history = [];
    if ($rows) {
        $ids = array_column($rows, 'id');
        $marks = implode(',', array_fill(0, count($ids), '?'));       // "?,?,?" with one ? per order
        $h = db()->prepare("SELECT order_id, status, note, created_at FROM order_status_history WHERE order_id IN ($marks) ORDER BY created_at, id");
        $h->execute($ids);
        foreach ($h as $row) {
            $history[(int) $row['order_id']][] = [
                'status' => $row['status'], 'note' => $row['note'], 'at' => date('c', strtotime($row['created_at'])),
            ];
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $oid = (int) $r['id'];
        $out[] = [
            'id' => $oid, 'status' => $r['status'], 'storeId' => (int) $r['store_id'], 'storeName' => $r['store_name'],
            'bag' => $r['bag_code'], 'bagLabel' => $r['bag_name'] . ' / ' . $r['weight_kg'] . 'kg',
            'payment' => $r['payment_method'], 'paymentStatus' => $r['payment_status'],
            'storeFee' => (float) $r['store_fee'], 'sendFee' => (float) $r['send_fee'], 'returnFee' => (float) $r['return_fee'],
            'total' => (float) $r['total_price'], 'freeDelivery' => (bool) $r['free_delivery'],
            'distanceKm' => (float) $r['distance_km'], 'notes' => $r['notes'],
            'storeRejectionReason' => $r['store_rejection_reason'],
            'createdAt' => date('c', strtotime($r['created_at'])),
            'history' => isset($history[$oid]) ? $history[$oid] : [],
        ];
    }
    return $out;
}

/** Customer actions on an existing order: 'cancel' (only before the store answers) or 'confirm_return'. */
function customer_order_action($customerId, $orderId, $action)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Lock the order row so a double-click (or the store acting at the same moment) cannot run this twice.
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND customer_id = ?' . db_lock_clause());
        $st->execute([$orderId, $customerId]);
        $o = $st->fetch();
        if (!$o) {
            throw new UserError('Order not found.');
        }
        $stale = 'This order was already updated. The list has been refreshed.';

        if ($action === 'cancel') {
            if ($o['status'] !== 'pending_store') {
                throw new UserError($stale);
            }
            store_order_set_status($o['id'], 'cancelled', $customerId, 'Customer cancelled order');

        } elseif ($action === 'confirm_return') {
            if ($o['status'] !== 'enroute_to_customer') {
                throw new UserError($stale);
            }
            $now = now();
            $pdo->prepare("UPDATE delivery_jobs SET status = 'completed', completed_at = ? WHERE order_id = ? AND job_type = 'return'")
                ->execute([$now, $o['id']]);
            $pdo->prepare('UPDATE orders SET completed_at = ? WHERE id = ?')->execute([$now, $o['id']]);
            store_order_set_status($o['id'], 'completed', $customerId, 'Customer confirmed return');

        } else {
            throw new UserError('Unknown action.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
