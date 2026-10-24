<?php
/** POST JSON {"role": "customer|rider|store", "name", "email", "password", "contact", "address"?}  ->  {"ok": true, "redirect", "status"} */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/auth/accounts.php';

api_run(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_fail('Method not allowed.', 405);
    }
    $in = json_input();
    $result = account_signup(isset($in['role']) ? (string) $in['role'] : '', $in);
    json_out(['ok' => true] + $result);
});
