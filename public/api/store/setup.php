<?php
/**
 * POST (multipart) -> submit the Store Setup form for admin review.
 * The store account moves to 'pending_approval' and is signed out until an admin approves it.
 */
require dirname(__DIR__, 3) . '/src/bootstrap.php';
require_once LV_ROOT . '/src/store/store_repo.php';

api_run(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_fail('Method not allowed.', 405);
    }
    $user = require_store_api(['setup_required']);
    csrf_check();
    $values = store_validate_input($_POST, false);
    $images = store_receive_images($user['id']);
    try {
        store_save($user['id'], $values, $images, true);
    } catch (Throwable $e) {
        delete_upload($images['image']);
        delete_upload($images['banner']);
        throw $e;
    }
    sign_out_pending_store($user['id']);        // signed out, but the pending page remembers which application to show
    json_out(['ok' => true, 'redirect' => pending_store_url()]);
});
