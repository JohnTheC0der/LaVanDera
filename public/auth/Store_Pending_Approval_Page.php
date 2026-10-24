<?php
/**
 * Store application status (pending / approved / rejected).
 * The store is normally signed out when it lands here (setup.php signs it out after submitting),
 * so the page identifies the application from $_SESSION['pending_store_id'] (set by
 * sign_out_pending_store / remember_pending_store) or from the current store session.
 * Only the status is shown - nothing about the store can be changed from this page.
 */
require __DIR__ . '/../../src/bootstrap.php';

$id = null;
$me = current_user();
if ($me && $me['role'] === 'store') {
    $id = (int) $me['id'];
} elseif (!empty($_SESSION['pending_store_id'])) {
    $id = (int) $_SESSION['pending_store_id'];
}

$status = null;
$note   = null;
if ($id) {
    $st = db()->prepare("SELECT status, status_note FROM users WHERE id = ? AND role = 'store'");
    $st->execute([$id]);
    if ($row = $st->fetch()) {
        $status = $row['status'];
        $note   = $row['status_note'];
    }
}

$login = config('login_file');                      // link inside public/auth/
$icon  = 'fa-hourglass-half';
$title = 'Application pending review';
$message = 'Your store details have been submitted. You can sign in after an admin approves your account.';
$action = '<a class="lv-btn lv-btn-outline" href="' . h($login) . '">Back to sign in</a>';

if ($status === null) {
    $title   = 'Store application not found';
    $message = 'Sign in with your store account to check its application status.';
    $action  = '<a class="lv-btn lv-btn-primary" href="' . h($login) . '">Go to sign in</a>';
} elseif ($status === 'active') {
    $title   = 'Your store is approved';
    $message = 'Your store account has been approved. Sign in to manage your store and orders.';
    $icon    = 'fa-circle-check';
    $action  = '<a class="lv-btn lv-btn-primary" href="' . h($login) . '">Sign in to your store</a>';
} elseif ($status === 'rejected') {
    $title   = 'Application not approved';
    $message = $note ?: 'Your application was not approved. Please contact LaVanDera support for more information.';
    $icon    = 'fa-circle-xmark';
} elseif ($status === 'setup_required') {
    $title   = 'Store setup is not complete';
    $message = 'Complete your store details and pricing before your application can be reviewed.';
    $action  = '<a class="lv-btn lv-btn-primary" href="' . h($login) . '">Sign in and finish setup</a>';
} elseif ($status === 'suspended') {
    $title   = 'Store account suspended';
    $message = 'Your store account is suspended. Please contact LaVanDera support for assistance.';
    $icon    = 'fa-ban';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Application Status - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <style>
        .approval-card { max-width: 700px; margin: 40px auto; text-align: center; }
        .approval-icon { width: 72px; height: 72px; margin: 0 auto 18px; display: grid; place-items: center; border-radius: 50%; background: var(--lv-pink-tint); color: var(--lv-plum); font-size: 30px; }
    </style>
</head>
<body>
    <main class="lv-container">
        <section class="lv-card lv-card-pad approval-card">
            <div class="approval-icon"><i id="approvalIcon" class="fa-solid <?= h($icon) ?>"></i></div>
            <div class="lv-eyebrow">Store partner application</div>
            <h1 id="approvalTitle" class="lv-title" style="font-size:32px;"><?= h($title) ?></h1>
            <p id="approvalMessage" class="lv-sub" style="max-width:none;"><?= h($message) ?></p>
            <div id="approvalActions" class="lv-row" style="justify-content:center; gap:12px; flex-wrap:wrap; margin-top:22px;"><?= $action ?></div>
        </section>
    </main>
</body>
</html>
