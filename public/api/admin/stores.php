<?php
/**
 * GET        -> all stores.          GET ?id=5 -> one store with details.
 * POST (JSON) {"action": "approve|reject|suspend|feature|unfeature", "id": 5, "reason": "..."} -> returns the refreshed list.
 *   approve also covers "reactivate" (suspended -> active) and approving a rejected store.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/admin/admin_repo.php';

api_run(function () {
    $admin = require_admin_api();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $in = json_input();
        $action = isset($in['action']) ? (string) $in['action'] : '';
        if ($action === 'feature' || $action === 'unfeature') {
            admin_set_store_featured((int) (isset($in['id']) ? $in['id'] : 0), $action === 'feature');
        } else {
            admin_set_user_status($admin['id'], 'store', (int) (isset($in['id']) ? $in['id'] : 0),
                $action, isset($in['reason']) ? (string) $in['reason'] : '');
        }
    } elseif (isset($_GET['id'])) {
        json_out(['ok' => true, 'store' => admin_store_detail((int) $_GET['id'])]);
    }
    json_out(['ok' => true, 'stores' => admin_stores()]);
});
