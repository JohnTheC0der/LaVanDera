<?php
/** GET -> all customers + totals.   GET ?id=5 -> one customer with their orders. (Read-only.) */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/admin/admin_repo.php';

api_run(function () {
    require_admin_api();
    if (isset($_GET['id'])) {
        json_out(['ok' => true, 'customer' => admin_customer_detail((int) $_GET['id'])]);
    }
    json_out(['ok' => true] + admin_customers());
});
