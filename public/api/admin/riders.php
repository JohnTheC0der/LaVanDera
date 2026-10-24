<?php
/**
 * GET        -> all riders.          GET ?id=5 -> one rider with details.
 * POST (JSON) {"action": "approve|reject|suspend", "id": 5, "reason": "..."} -> returns the refreshed list.
 *   approve also covers "reactivate" (suspended -> active) and approving a rejected rider.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/admin/admin_repo.php';

api_run(function () {
    $admin = require_admin_api();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $in = json_input();
        admin_set_user_status($admin['id'], 'rider', (int) (isset($in['id']) ? $in['id'] : 0),
            isset($in['action']) ? (string) $in['action'] : '', isset($in['reason']) ? (string) $in['reason'] : '');
    } elseif (isset($_GET['id'])) {
        json_out(['ok' => true, 'rider' => admin_rider_detail((int) $_GET['id'])]);
    }
    json_out(['ok' => true, 'riders' => admin_riders()]);
});
