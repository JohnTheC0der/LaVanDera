<?php
/**
 * Rider application status ("wait page"): pending / approved / rejected / suspended.
 * Modeled on Store_Pending_Approval_Page.php. A rider who just signed up (or tried to sign in
 * while unapproved) is NOT signed in, so we find them through $_SESSION['pending_rider_id'].
 * This page only DISPLAYS a status. Nothing can be changed from here.
 */
require __DIR__ . '/../../src/bootstrap.php';
require_once LV_ROOT . '/src/auth/guards.php';

// 1. Who is this? A signed-in rider, or the rider we remembered at sign-up / sign-in.
$id = null;
$me = current_user();
if ($me && $me['role'] === 'rider') {
    $id = (int) $me['id'];
} elseif (!empty($_SESSION['pending_rider_id'])) {
    $id = (int) $_SESSION['pending_rider_id'];
}

// 2. Read their CURRENT status from the database, so an admin approval shows up on the next refresh.
$status = null;
$note   = null;
if ($id) {
    $st = db()->prepare("SELECT status, status_note FROM users WHERE id = ? AND role = 'rider'");
    $st->execute([$id]);
    if ($row = $st->fetch()) {
        $status = $row['status'];
        $note   = $row['status_note'];
    }
}

// 3. Pick the wording. Defaults describe the normal case: waiting for approval.
$login   = config('login_file');
$icon    = 'fa-hourglass-half';
$title   = 'Rider application pending review';
$message = 'Thanks for signing up! An admin is reviewing your rider application. You can sign in after it is approved.';
$action  = '<a class="lv-btn lv-btn-outline" href="' . h($login) . '">Back to sign in</a>';

if ($status === null) {
    $title   = 'Rider application not found';
    $message = 'Sign in with your rider account to check your application status.';
    $action  = '<a class="lv-btn lv-btn-primary" href="' . h($login) . '">Go to sign in</a>';
} elseif ($status === 'active') {
    $title   = 'You are approved!';
    $message = 'Your rider account has been approved. Sign in to start accepting deliveries.';
    $icon    = 'fa-circle-check';
    $action  = '<a class="lv-btn lv-btn-primary" href="' . h($login) . '">Sign in as a rider</a>';
} elseif ($status === 'rejected') {
    $title   = 'Application not approved';
    $message = $note ?: 'Your rider application was not approved. Please contact LaVanDera support for more information.';
    $icon    = 'fa-circle-xmark';
} elseif ($status === 'suspended') {
    $title   = 'Rider account suspended';
    $message = 'Your rider account is suspended. Please contact LaVanDera support for assistance.';
    $icon    = 'fa-ban';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Application Status - LaVanDera</title>
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
            <div class="approval-icon"><i class="fa-solid <?= h($icon) ?>"></i></div>
            <div class="lv-eyebrow">Rider partner application</div>
            <h1 class="lv-title" style="font-size:32px;"><?= h($title) ?></h1>
            <p class="lv-sub" style="max-width:none;"><?= h($message) ?></p>
            <div class="lv-row" style="justify-content:center; gap:12px; flex-wrap:wrap; margin-top:22px;"><?= $action ?></div>
        </section>
    </main>
</body>
</html>
