<?php
/** POST JSON {"email": "...", "password": "..."}  ->  {"ok": true, "redirect": "../customer/..."} */
require dirname(__DIR__, 3) . '/src/bootstrap.php';          // public/api/auth -> up 3 = project root
require_once LV_ROOT . '/src/auth/accounts.php';

api_run(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_fail('Method not allowed.', 405);
    }
    // No csrf_check() here on purpose: the visitor has no session yet, so there is nothing to forge.
    $in = json_input();
    $result = account_login(
        isset($in['email']) ? $in['email'] : '',
        isset($in['password']) ? $in['password'] : ''
    );
    json_out(['ok' => true] + $result);
});
