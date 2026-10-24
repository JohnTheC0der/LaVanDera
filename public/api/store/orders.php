<?php
/**
 * GET  -> all orders of the signed-in store.
 * POST -> JSON {"action": "confirm|reject|confirm_delivery|ready_for_return", "order_id": 12, "reason": "..."}
 *         Returns the refreshed order list.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/store/store_orders.php';

api_run(function () {
    $user = require_store_api(['active']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $in = json_input();
        store_order_action(
            $user['id'],
            $user['id'],
            (int) (isset($in['order_id']) ? $in['order_id'] : 0),
            isset($in['action']) ? (string) $in['action'] : '',
            isset($in['reason']) ? (string) $in['reason'] : null
        );
    }
    json_out(['ok' => true, 'orders' => store_orders_list($user['id'])]);
});
