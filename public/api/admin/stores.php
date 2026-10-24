<?php
/**
 * GET        -> all stores.          GET ?id=5 -> one store with details.
 * POST (JSON) {"action": "approve|reject|suspend", "id": 5, "reason": "..."} -> returns the refreshed list.
 *   approve also covers "reactivate" (suspended -> active) and approving a rejected store.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/admin/admin_repo.php';

api_run(function () {
    $admin = require_admin_api();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $in = json_input();
        admin_set_user_status($admin['id'], 'store', (int) (isset($in['id']) ? $in['id'] : 0),
            isset($in['action']) ? (string) $in['action'] : '', isset($in['reason']) ? (string) $in['reason'] : '');
    } elseif (isset($_GET['id'])) {
        json_out(['ok' => true, 'store' => admin_store_detail((int) $_GET['id'])]);
    }
    json_out(['ok' => true, 'stores' => admin_stores()]);
});
