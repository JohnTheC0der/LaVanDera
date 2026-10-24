<?php
/**
 * GET  -> all of this customer's orders (with timelines).
 * POST -> JSON {"action": "place|cancel|confirm_return", ...}
 *           place          needs store_id, bag, payment, notes   -> also returns "orderId"
 *           cancel         needs order_id
 *           confirm_return needs order_id
 *         Always returns the refreshed order list.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/auth/guards.php';
require_once LV_ROOT . '/src/customer/customer_repo.php';

api_run(function () {
    $user = require_customer_api();
    $newOrderId = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $in     = json_input();
        $action = isset($in['action']) ? (string) $in['action'] : '';
        if ($action === 'place') {
            $newOrderId = customer_place_order($user['id'], $in);
        } else {
            customer_order_action($user['id'], (int) (isset($in['order_id']) ? $in['order_id'] : 0), $action);
        }
    }
    $reply = ['ok' => true, 'orders' => customer_orders_list($user['id'])];
    if ($newOrderId !== null) {
        $reply['orderId'] = $newOrderId;
    }
    json_out($reply);
});
