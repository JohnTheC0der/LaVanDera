<?php
require __DIR__ . '/../../src/bootstrap.php';
require_once LV_ROOT . '/src/store/store_repo.php';
$user  = guard_store_page('setup');
$store = store_load($user['id']);

$blocked = null;
if ($user['status'] === 'rejected') {
    $blocked = 'Your store application was rejected' . ($user['status_note'] ? ': ' . $user['status_note'] : '. Contact support for assistance.');
} elseif ($user['status'] === 'suspended') {
    $blocked = 'Your store account is suspended. Contact support for assistance.';
}
$prices    = $store['prices'] + ['small' => 50, 'medium' => 90, 'large' => 130];   // suggested starting prices
$lvConfig  = ['loginUrl' => config('login_url'), 'logoutUrl' => '../auth/logout.php', 'csrf' => csrf_token()];
$lvSession = ['id' => $user['id'], 'role' => 'store', 'name' => $user['name'], 'email' => $user['email']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Your Store - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <style>
        .setup-form { max-width: 900px; margin: 0 auto 48px; }
        .setup-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .setup-grid .full { grid-column: 1 / -1; }
        .setup-note { padding: 14px 16px; border-radius: 12px; background: var(--lv-pink-tint); color: var(--lv-ink); }
        .setup-map { height: 280px; border-radius: 14px; border: 1px solid var(--lv-line, #e6d5df); z-index: 0; }
        .setup-map-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 8px 0; }
        .setup-dialog-overlay { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(38, 0, 26, .58); }
        .setup-dialog-overlay.open { display: flex; }
        .setup-dialog { width: min(100%, 460px); padding: 24px; border-radius: 18px; background: #fff; box-shadow: 0 20px 60px rgba(35, 0, 24, .3); }
        .setup-dialog h2 { margin: 0 0 10px; color: var(--lv-plum); }
        .setup-dialog p { margin: 0; color: var(--lv-ink); }
        .setup-dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
        @media (max-width: 480px) { .setup-dialog-actions { flex-direction: column-reverse; } .setup-dialog-actions .lv-btn { width: 100%; } }
        @media (max-width: 640px) { .setup-grid { grid-template-columns: 1fr; } .setup-grid .full { grid-column: auto; } }
    </style>
</head>
<body>
    <main class="lv-container">
        <section class="lv-page-head">
            <div class="lv-eyebrow">Store partner application</div>
            <h1 class="lv-title">Set up your store</h1>
            <p class="lv-sub">Add your store details and confirm your laundry prices. An admin will review your application before you can sign in to the store portal.</p>
        </section>
<?php if ($blocked): ?>
        <section class="lv-card lv-card-pad setup-form">
            <h2 class="lv-section-title">Store application unavailable</h2>
            <p class="lv-sub"><?= h($blocked) ?></p>
            <a class="lv-btn lv-btn-outline lv-mt" href="../auth/logout.php">Sign out</a>
        </section>
<?php else: ?>
        <form id="setupForm" class="lv-card lv-card-pad setup-form" enctype="multipart/form-data">
            <p class="setup-note">All three bag prices are required. Images are optional (JPG, PNG, GIF or WebP, 2 MB or smaller each).</p>
            <div class="setup-grid" style="margin-top:18px;">
                <div class="lv-field">
                    <label class="lv-label" for="storeName">Store name</label>
                    <input class="lv-input" id="storeName" name="store_name" maxlength="80" required value="<?= h($store['name']) ?>">
                </div>
                <div class="lv-field">
                    <label class="lv-label" for="contact">Contact number</label>
                    <input class="lv-input" id="contact" name="contact" maxlength="20" required value="<?= h($store['contact']) ?>">
                </div>
                <div class="lv-field full">
                    <label class="lv-label" for="address">Store address</label>
                    <input class="lv-input" id="address" name="address" maxlength="180" required value="<?= h($store['address']) ?>">
                </div>
                <div class="lv-field full">
                    <label class="lv-label">Pin your store on the map (used to calculate delivery fees)</label>
                    <div class="setup-map-bar">
                        <button class="lv-btn lv-btn-outline lv-btn-sm" type="button" id="findOnMap"><i class="fa-solid fa-location-dot"></i> Find address on map</button>
                        <span id="mapStatus" class="lv-sub" style="max-width:none;"></span>
                    </div>
                    <div id="setupMap" class="setup-map"></div>
                    <input type="hidden" id="latitude" name="latitude" value="<?= h($store['latitude']) ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?= h($store['longitude']) ?>">
                </div>
                <div class="lv-field full">
                    <label class="lv-label" for="description">Store description</label>
                    <textarea class="lv-textarea" id="description" name="description" maxlength="500" rows="4" placeholder="Tell customers about your services and what makes your store special."><?= h($store['description']) ?></textarea>
                </div>
                <div class="lv-field">
                    <label class="lv-label" for="image">Store profile picture</label>
                    <input class="lv-input" id="image" name="image" type="file" accept="image/*">
                </div>
                <div class="lv-field">
                    <label class="lv-label" for="banner">Store banner</label>
                    <input class="lv-input" id="banner" name="banner" type="file" accept="image/*">
                </div>
            </div>
            <h2 class="lv-section-title" style="margin:24px 0 12px;">Confirm your prices</h2>
            <div class="setup-grid">
                <div class="lv-field">
                    <label class="lv-label" for="smallPrice">Small / 5kg bag (PHP)</label>
                    <input class="lv-input" id="smallPrice" name="price_small" type="number" min="1" step="1" value="<?= h($prices['small'] + 0) ?>" required>
                </div>
                <div class="lv-field">
                    <label class="lv-label" for="mediumPrice">Medium / 10kg bag (PHP)</label>
                    <input class="lv-input" id="mediumPrice" name="price_medium" type="number" min="1" step="1" value="<?= h($prices['medium'] + 0) ?>" required>
                </div>
                <div class="lv-field">
                    <label class="lv-label" for="largePrice">Large / 15kg bag (PHP)</label>
                    <input class="lv-input" id="largePrice" name="price_large" type="number" min="1" step="1" value="<?= h($prices['large'] + 0) ?>" required>
                </div>
            </div>
            <p id="setupError" class="lv-error" role="alert" style="margin-top:14px;"></p>
            <div class="lv-row-between" style="gap:12px; flex-wrap:wrap; margin-top:16px;">
                <button class="lv-btn lv-btn-outline" type="button" id="signOutButton">Sign out</button>
                <button id="submitBtn" class="lv-btn lv-btn-pink" type="submit">Submit for admin review</button>
            </div>
        </form>
<?php endif; ?>
    </main>
    <div class="setup-dialog-overlay" id="setupDialog" aria-hidden="true">
        <section class="setup-dialog" role="dialog" aria-modal="true" aria-labelledby="setupDialogTitle" aria-describedby="setupDialogMessage" tabindex="-1">
            <h2 id="setupDialogTitle"></h2>
            <p id="setupDialogMessage"></p>
            <div class="setup-dialog-actions">
                <button class="lv-btn lv-btn-outline" type="button" id="cancelSetupDialog">Cancel</button>
                <button class="lv-btn lv-btn-primary" type="button" id="confirmSetupDialog">Confirm</button>
            </div>
        </section>
    </div>
    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="../js/lavandera_map.js"></script>
<?php if (!$blocked): ?>
    <script>
        const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
        const form = document.getElementById('setupForm');

        LVMap.picker({
            mapEl: document.getElementById('setupMap'), address: document.getElementById('address'),
            findBtn: document.getElementById('findOnMap'), lat: document.getElementById('latitude'),
            lng: document.getElementById('longitude'), status: document.getElementById('mapStatus'),
            initial: { lat: document.getElementById('latitude').value, lng: document.getElementById('longitude').value }
        });

        const setupDialog = document.getElementById('setupDialog');
        let setupDialogAction = null;
        let setupDialogTrigger = null;

        function closeSetupDialog(restoreFocus) {
            setupDialog.classList.remove('open');
            setupDialog.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            setupDialogAction = null;
            if (restoreFocus && setupDialogTrigger && setupDialogTrigger.isConnected) setupDialogTrigger.focus();
        }

        function openSetupDialog(title, message, options) {
            options = options || {};
            setupDialogTrigger = document.activeElement;
            document.getElementById('setupDialogTitle').textContent = title;
            document.getElementById('setupDialogMessage').textContent = message;
            document.getElementById('cancelSetupDialog').hidden = options.cancelText === false;
            document.getElementById('cancelSetupDialog').textContent = options.cancelText || 'Cancel';
            const confirmButton = document.getElementById('confirmSetupDialog');
            confirmButton.textContent = options.confirmText || 'Confirm';
            setupDialogAction = options.onConfirm || null;
            setupDialog.classList.add('open');
            setupDialog.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            (options.focusConfirm || options.cancelText === false
                ? confirmButton
                : document.getElementById('cancelSetupDialog')).focus();
        }

        document.getElementById('cancelSetupDialog').addEventListener('click', function () { closeSetupDialog(true); });
        document.getElementById('confirmSetupDialog').addEventListener('click', function () {
            const action = setupDialogAction;
            closeSetupDialog(false);
            if (action) action();
        });
        setupDialog.addEventListener('click', function (event) {
            if (event.target === setupDialog) closeSetupDialog(true);
        });
        document.addEventListener('keydown', function (event) {
            if (!setupDialog.classList.contains('open')) return;
            if (event.key === 'Escape') closeSetupDialog(true);
            else if (event.key === 'Tab') {
                const focusable = Array.from(setupDialog.querySelectorAll('button:not([hidden])'));
                if (focusable.length < 2) {
                    event.preventDefault();
                    return;
                }
                if (event.shiftKey && document.activeElement === focusable[0]) {
                    event.preventDefault();
                    focusable[focusable.length - 1].focus();
                } else if (!event.shiftKey && document.activeElement === focusable[focusable.length - 1]) {
                    event.preventDefault();
                    focusable[0].focus();
                }
            }
        });

        document.getElementById('signOutButton').addEventListener('click', function () {
            LV.requestLogout(this, 'Are you sure you want to sign out before submitting your store application?');
        });

        async function submitSetupApplication() {
            const button = document.getElementById('submitBtn');
            button.disabled = true;
            try {
                const result = await LVApi.postForm('../api/store/setup.php', new FormData(form));
                try { localStorage.removeItem('lv_session'); } catch (e) { /* ignore */ }
                openSetupDialog('Application submitted', 'Your store details are waiting for admin review.', {
                    cancelText: false,
                    confirmText: 'View application status',
                    onConfirm: function () { location.href = result.redirect; }
                });
            } catch (saveError) {
                openSetupDialog('Unable to submit application', saveError.message || 'Unable to submit your store application.', {
                    cancelText: false,
                    confirmText: 'OK'
                });
                button.disabled = false;
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            for (const id of ['image', 'banner']) {
                const file = document.getElementById(id).files[0];
                if (file && !file.type.startsWith('image/')) { openSetupDialog('Check your images', 'Choose a valid image file.', { cancelText: false, confirmText: 'OK' }); return; }
                if (file && file.size > MAX_IMAGE_BYTES) { openSetupDialog('Check your images', 'Each image must be 2 MB or smaller.', { cancelText: false, confirmText: 'OK' }); return; }
            }
            for (const id of ['smallPrice', 'mediumPrice', 'largePrice']) {
                const price = Number(document.getElementById(id).value);
                if (!Number.isFinite(price) || price <= 0) {
                    openSetupDialog('Check your prices', 'Enter a valid price above zero for each bag size.', { cancelText: false, confirmText: 'OK' });
                    return;
                }
            }
            if (!document.getElementById('latitude').value || !document.getElementById('longitude').value) {
                openSetupDialog('Pin your store location', 'Press "Find address on map" (or click the map) so delivery fees can be calculated.', { cancelText: false, confirmText: 'OK' });
                return;
            }
            openSetupDialog('Submit for admin review?', 'After submission, your store account will remain pending until an administrator reviews it.', {
                cancelText: 'Continue editing',
                confirmText: 'Submit application',
                focusConfirm: true,
                onConfirm: submitSetupApplication
            });
        });
    </script>
<?php endif; ?>
</body>
</html>
