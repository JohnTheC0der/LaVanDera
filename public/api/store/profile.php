<?php
/**
 * GET  -> the signed-in store's profile, prices, benefits, order counts, and the benefit catalog.
 * POST -> save the Store Dashboard form (multipart: fields + optional `image` / `banner` files).
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/store/store_repo.php';

api_run(function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = require_store_api(['active']);          // rejected/suspended stores are read-only
        csrf_check();
        $values = store_validate_input($_POST, true);
        $images = store_receive_images($user['id']);
        try {
            store_save($user['id'], $values, $images, false);
        } catch (Throwable $e) {
            delete_upload($images['image']);            // don't leave orphan files behind
            delete_upload($images['banner']);
            throw $e;
        }
    } else {
        $user = require_store_api(['active', 'rejected', 'suspended']);
    }
    json_out([
        'ok'             => true,
        'store'          => store_load($user['id']),
        'stats'          => store_stats($user['id']),
        'benefitCatalog' => store_benefit_catalog(),
    ]);
});
