<?php
/** GET -> headline numbers, order status counts, pending approvals and the 8 newest orders. */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/admin/admin_repo.php';

api_run(function () {
    require_admin_api();
    json_out(['ok' => true] + admin_dashboard());
});
