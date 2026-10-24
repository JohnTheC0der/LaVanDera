<?php
/** GET -> every approved store with this customer's distance + fee, plus the fee rule used. */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/auth/guards.php';
require_once LV_ROOT . '/src/customer/customer_repo.php';

api_run(function () {
    $user = require_customer_api();
    json_out([
        'ok'     => true,
        'stores' => customer_stores_list($user['id']),
        'feeRule' => [                                   // lets the page print "P40 for the first 2 km + P10 per extra km"
            'base'   => (float) setting('base_delivery_fee', 40),
            'baseKm' => (float) setting('base_distance_km', 2),
            'perKm'  => (float) setting('per_km_fee', 10),
        ],
    ]);
});
