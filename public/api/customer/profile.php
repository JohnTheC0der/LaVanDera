<?php
/**
 * GET  -> the signed-in customer's profile + order stats.
 * POST -> JSON {"contact", "address", "latitude", "longitude"} saves them.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/auth/guards.php';
require_once LV_ROOT . '/src/customer/customer_repo.php';

api_run(function () {
    $user = require_customer_api();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();                                    // POSTs change data, so they need the CSRF token
        customer_update_profile($user['id'], json_input());
    }
    json_out(['ok' => true, 'customer' => customer_load($user['id']), 'stats' => customer_stats($user['id'])]);
});
