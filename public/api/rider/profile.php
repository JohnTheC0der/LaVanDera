<?php
/** GET -> rider profile + stats.  POST JSON {"name", "contact"} -> saves them. */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/auth/guards.php';
require_once LV_ROOT . '/src/rider/rider_repo.php';

api_run(function () {
    $user = require_rider_api();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        rider_update_profile($user['id'], json_input());
    }
    json_out(['ok' => true, 'rider' => rider_load($user['id']), 'stats' => rider_stats($user['id'])]);
});
