<?php
/**
 * Store Orders page logic.
 *
 * ORDER FLOW (who changes what):
 *   customer places order ............ orders.status = pending_store                (customer code)
 *   store CONFIRMS ................... -> waiting_for_driver, creates the 'send' job  (this file)
 *   rider accepts send job ........... delivery_jobs: available -> accepted
 *                                      orders.status -> enroute_to_store             (RIDER code must do this)
 *   store CONFIRMS DELIVERY .......... -> at_laundromat, send job completed,
 *                                      cash orders become paid                       (this file)
 *   store RETURN TO CUSTOMER ......... -> waiting_for_return_driver, creates the 'return' job (this file)
 *   rider accepts return job ......... orders.status -> enroute_to_customer           (RIDER code)
 *   customer CONFIRM RETURN .......... -> completed, return job completed             (CUSTOMER code)
 *   store REJECTS (while pending) .... -> rejected
 */

function store_orders_list($storeId)
{
    $st = db()->prepare(
        'SELECT o.id, o.status, o.payment_method, o.payment_status, o.store_fee, o.send_fee, o.return_fee,
                o.total_price, o.free_delivery, o.customer_address, o.notes, o.store_rejection_reason, o.created_at,
                b.code AS bag_code, b.name AS bag_name, b.weight_kg,
                c.full_name, c.contact_no
         FROM orders o
         JOIN bag_types b ON b.id = o.bag_type_id
         JOIN customers c ON c.user_id = o.customer_id
         WHERE o.store_id = ?
         ORDER BY o.created_at DESC, o.id DESC'
    );
    $st->execute([$storeId]);
    $out = [];
    foreach ($st as $r) {
        $out[] = [
            'id'              => (int) $r['id'],
            'status'          => $r['status'],
            'customerName'    => $r['full_name'],
            'customerContact' => $r['contact_no'],
            'customerAddress' => $r['customer_address'],
            'bag'             => $r['bag_code'],
            'bagLabel'        => $r['bag_name'] . ' / ' . $r['weight_kg'] . 'kg',
            'storeFee'        => (float) $r['store_fee'],
            'sendFee'         => (float) $r['send_fee'],
            'returnFee'       => (float) $r['return_fee'],
            'total'           => (float) $r['total_price'],
            'freeDelivery'    => (bool) $r['free_delivery'],
            'payment'         => $r['payment_method'],
            'paymentStatus'   => $r['payment_status'],
            'notes'           => $r['notes'],
            'rejectionReason' => $r['store_rejection_reason'],
            'createdAt'       => date('c', strtotime($r['created_at'])),
        ];
    }
    return $out;
}

/**
 * Apply one store action to an order. $action: confirm | reject | confirm_delivery | ready_for_return
 * Everything happens in one transaction with the order row locked, so two clicks
 * (or a customer cancelling at the same moment) can never double-process an order.
 */
function store_order_action($storeId, $userId, $orderId, $action, $reason = null)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND store_id = ?' . db_lock_clause());
        $st->execute([$orderId, $storeId]);
        $o = $st->fetch();
        if (!$o) {
            throw new UserError('Order not found.');
        }
        $stale = 'This order was already updated. The list has been refreshed.';
        $now   = now();

        if ($action === 'confirm') {
            if ($o['status'] !== 'pending_store') {
                throw new UserError($stale);
            }
            store_order_create_job($o, 'send', $o['customer_address'], $o['store_address']);
            store_order_set_status($o['id'], 'waiting_for_driver', $userId, 'Store confirmed order');

        } elseif ($action === 'reject') {
            if ($o['status'] !== 'pending_store') {
                throw new UserError($stale);
            }
            $reason = trim((string) $reason);
            $reason = $reason === '' ? 'Rejected by store' : mb_substr($reason, 0, 255);
            $pdo->prepare('UPDATE orders SET store_rejection_reason = ? WHERE id = ?')->execute([$reason, $o['id']]);
            store_order_set_status($o['id'], 'rejected', $userId, $reason);

        } elseif ($action === 'confirm_delivery') {
            if ($o['status'] !== 'enroute_to_store') {
                throw new UserError($stale);
            }
            $pdo->prepare("UPDATE delivery_jobs SET status = 'completed', completed_at = ? WHERE order_id = ? AND job_type = 'send'")
                ->execute([$now, $o['id']]);
            if ($o['payment_method'] === 'cash' && $o['payment_status'] !== 'paid') {
                $pdo->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$o['id']]);
            }
            store_order_set_status($o['id'], 'at_laundromat', $userId, 'Store confirmed laundry delivery and payment');

        } elseif ($action === 'ready_for_return') {
            if ($o['status'] !== 'at_laundromat') {
                throw new UserError($stale);
            }
            store_order_create_job($o, 'return', $o['store_address'], $o['customer_address']);
            store_order_set_status($o['id'], 'waiting_for_return_driver', $userId, 'Store marked order ready for return');

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

function store_order_set_status($orderId, $status, $userId, $note)
{
    $pdo = db();
    $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by, note, created_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$orderId, $status, $userId, $note, now()]);
}

/** Post a job to the Rider Available Orders board. Rider pay follows the distance rule in settings. */
function store_order_create_job(array $order, $type, $pickup, $dropoff)
{
    db()->prepare(
        'INSERT INTO delivery_jobs (order_id, job_type, status, pickup_address, dropoff_address, distance_km, rider_pay, created_at)
         VALUES (?, ?, \'available\', ?, ?, ?, ?, ?)'
    )->execute([$order['id'], $type, $pickup, $dropoff, $order['distance_km'], calc_delivery_fee($order['distance_km']), now()]);
}
