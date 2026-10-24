<?php
require __DIR__ . '/../../src/bootstrap.php';
$user = guard_store_page('portal');
$lvConfig  = ['loginUrl' => config('login_url'), 'logoutUrl' => '../auth/logout.php', 'csrf' => csrf_token()];
$lvSession = ['id' => $user['id'], 'role' => 'store', 'name' => $user['name'], 'email' => $user['email']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Dashboard - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <style>
        .store-banner { width: 100%; height: 210px; object-fit: cover; border-radius: 18px; background: var(--lv-pink-tint); }
        .store-avatar { width: 100px; height: 100px; margin-top: -50px; border: 5px solid #fff; border-radius: 22px; object-fit: cover; background: #fff; }
        .store-edit-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .store-edit-grid .full { grid-column: 1 / -1; }
        .store-benefits { display: flex; flex-wrap: wrap; gap: 12px 20px; }
        .store-benefits label { display: flex; align-items: center; gap: 8px; color: var(--lv-ink); }
        .store-benefits input, .store-toggle input { accent-color: var(--lv-plum); }
        .store-toggle { display: flex; align-items: center; gap: 10px; font-weight: 700; }
        .price-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .store-dialog-overlay { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(38, 0, 26, .58); }
        .store-dialog-overlay.open { display: flex; }
        .store-dialog { width: min(100%, 460px); padding: 24px; border-radius: 18px; background: #fff; box-shadow: 0 20px 60px rgba(35, 0, 24, .3); }
        .store-dialog h2 { margin: 0 0 10px; color: var(--lv-plum); }
        .store-dialog p { margin: 0; color: var(--lv-ink); }
        .store-dialog-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
        @media (max-width: 480px) { .store-dialog-actions { flex-direction: column-reverse; } .store-dialog-actions .lv-btn { width: 100%; } }
        .store-map { height: 280px; border-radius: 14px; border: 1px solid var(--lv-line, #e6d5df); z-index: 0; }
        .store-map-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 8px 0; }
        @media (max-width: 640px) {
            .store-edit-grid, .price-grid { grid-template-columns: 1fr; }
            .store-edit-grid .full { grid-column: auto; }
            .store-banner { height: 150px; }
        }
    </style>
</head>
<body>
    <div id="lv-nav"></div>
    <main class="lv-container">
        <section class="lv-page-head">
            <div class="lv-eyebrow">Partner portal</div>
            <h1 class="lv-title">Store Dashboard</h1>
            <p class="lv-sub">Manage your store details, prices, benefits, and incoming laundry orders.</p>
        </section>

        <section id="storeLoadError" class="lv-card lv-card-pad" hidden>
            <h2 class="lv-section-title">Could not load your store</h2>
            <p id="storeLoadMessage" class="lv-sub"></p>
            <button class="lv-btn lv-btn-primary lv-mt" onclick="location.reload()">Try again</button>
        </section>

        <div id="storePortal" hidden>
            <section class="lv-card lv-card-pad" style="margin-bottom:20px;">
                <img id="storeBanner" class="store-banner" alt="Store banner">
                <div class="lv-row-between" style="align-items:flex-end; gap:18px; flex-wrap:wrap;">
                    <div class="lv-row" style="align-items:flex-end; gap:16px;">
                        <img id="storeImage" class="store-avatar" alt="Store profile">
                        <div style="padding-bottom:8px;">
                            <h2 id="storeName" class="lv-section-title"></h2>
                            <div id="storeStatus" style="margin-top:6px;"></div>
                        </div>
                    </div>
                    <a href="Store_Orders_Page.php" class="lv-btn lv-btn-primary"><i class="fa-solid fa-boxes-stacked"></i> Manage orders</a>
                </div>
                <p id="storeDescription" class="lv-sub" style="max-width:none; margin-top:16px;"></p>
            </section>

            <section id="storeStateMessage" class="lv-card lv-card-pad" style="margin-bottom:20px;" hidden></section>

            <section class="lv-grid lv-grid-2" style="margin-bottom:20px;">
                <article class="lv-card lv-card-pad">
                    <div class="lv-eyebrow">Store contact</div>
                    <p class="lv-kv"><span>Email</span><strong id="storeEmail">-</strong></p>
                    <p class="lv-kv"><span>Phone</span><strong id="storeContact">-</strong></p>
                    <p class="lv-kv"><span>Address</span><strong id="storeAddress">-</strong></p>
                </article>
                <article class="lv-card lv-card-pad">
                    <div class="lv-eyebrow">Order overview</div>
                    <p class="lv-kv"><span>Total orders</span><strong id="totalOrders">0</strong></p>
                    <p class="lv-kv"><span>Needs attention</span><strong id="incomingOrders">0</strong></p>
                    <p class="lv-kv"><span>Completed</span><strong id="completedOrders">0</strong></p>
                    <a href="Store_Orders_Page.php" class="lv-btn lv-btn-outline lv-btn-sm lv-mt">View store orders</a>
                </article>
            </section>

            <section class="lv-card lv-card-pad">
                <div class="lv-row-between" style="flex-wrap:wrap; margin-bottom:18px;">
                    <div>
                        <div class="lv-eyebrow">Store settings</div>
                        <h2 class="lv-section-title">Edit store information</h2>
                    </div>
                    <label class="store-toggle"><input id="accepting" type="checkbox"> Accepting new orders</label>
                </div>
                <form id="storeForm">
                    <div class="store-edit-grid">
                        <div class="lv-field">
                            <label class="lv-label" for="editName">Store name</label>
                            <input class="lv-input" id="editName" required maxlength="80">
                        </div>
                        <div class="lv-field">
                            <label class="lv-label" for="editContact">Contact number</label>
                            <input class="lv-input" id="editContact" required maxlength="20">
                        </div>
                        <div class="lv-field full">
                            <label class="lv-label" for="editAddress">Store address</label>
                            <input class="lv-input" id="editAddress" required maxlength="180">
                        </div>
                        <div class="lv-field full">
                            <label class="lv-label">Store location (used to calculate delivery fees)</label>
                            <div class="store-map-bar">
                                <button class="lv-btn lv-btn-outline lv-btn-sm" type="button" id="findOnMap"><i class="fa-solid fa-location-dot"></i> Find address on map</button>
                                <span id="mapStatus" class="lv-sub" style="max-width:none;"></span>
                            </div>
                            <div id="storeMap" class="store-map"></div>
                            <input type="hidden" id="latitude" name="latitude">
                            <input type="hidden" id="longitude" name="longitude">
                        </div>
                        <div class="lv-field full">
                            <label class="lv-label" for="editDescription">Store description</label>
                            <textarea class="lv-textarea" id="editDescription" maxlength="500" rows="3"></textarea>
                        </div>
                        <div class="lv-field">
                            <label class="lv-label" for="editImage">Profile picture</label>
                            <input class="lv-input" id="editImage" type="file" accept="image/*">
                        </div>
                        <div class="lv-field">
                            <label class="lv-label" for="editBanner">Banner image</label>
                            <input class="lv-input" id="editBanner" type="file" accept="image/*">
                        </div>
                    </div>
                    <h3 class="lv-section-title" style="margin:18px 0 12px;">Bag prices</h3>
                    <div class="price-grid">
                        <div class="lv-field"><label class="lv-label" for="priceSmall">Small / 5kg (PHP)</label><input class="lv-input" id="priceSmall" type="number" min="1" step="1" required></div>
                        <div class="lv-field"><label class="lv-label" for="priceMedium">Medium / 10kg (PHP)</label><input class="lv-input" id="priceMedium" type="number" min="1" step="1" required></div>
                        <div class="lv-field"><label class="lv-label" for="priceLarge">Large / 15kg (PHP)</label><input class="lv-input" id="priceLarge" type="number" min="1" step="1" required></div>
                    </div>
                    <h3 class="lv-section-title" style="margin:14px 0 12px;">Store benefits</h3>
                    <div class="store-benefits" id="benefitOptions"></div>
                    <p class="lv-error" id="storeFormError" role="alert" style="margin-top:12px;"></p>
                    <button id="saveBtn" class="lv-btn lv-btn-pink lv-mt" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save store changes</button>
                </form>
            </section>
        </div>
    </main>
    <div class="store-dialog-overlay" id="storeDialog" aria-hidden="true">
        <section class="store-dialog" role="dialog" aria-modal="true" aria-labelledby="storeDialogTitle" aria-describedby="storeDialogMessage" tabindex="-1">
            <h2 id="storeDialogTitle">Store update</h2>
            <p id="storeDialogMessage"></p>
            <div class="store-dialog-actions">
                <button class="lv-btn lv-btn-outline" type="button" id="cancelStoreDialog">Close</button>
                <button class="lv-btn lv-btn-primary" type="button" id="confirmStoreDialog">OK</button>
            </div>
        </section>
    </div>

    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
    <script src="../js/lavandera_map.js"></script>
    <script>
        const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
        let storeRecord = null;
        let benefitCatalog = [];
        let mapPicker = null;

        const storeDialog = document.getElementById('storeDialog');
        let storeDialogCallback = null;
        let storeDialogTrigger = null;

        function openStoreDialog(title, message, options) {
            options = options || {};
            storeDialogTrigger = document.activeElement;
            document.getElementById('storeDialogTitle').textContent = title;
            document.getElementById('storeDialogMessage').textContent = message;
            document.getElementById('cancelStoreDialog').hidden = options.cancelText === false;
            document.getElementById('cancelStoreDialog').textContent = options.cancelText || 'Close';
            const confirmButton = document.getElementById('confirmStoreDialog');
            confirmButton.textContent = options.confirmText || 'OK';
            confirmButton.hidden = options.confirmText === false;
            storeDialogCallback = options.onConfirm || null;
            storeDialog.classList.add('open');
            storeDialog.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            (options.focusConfirm || options.cancelText === false
                ? confirmButton
                : document.getElementById('cancelStoreDialog')).focus();
        }

        function closeStoreDialog(restoreFocus) {
            storeDialog.classList.remove('open');
            storeDialog.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            storeDialogCallback = null;
            if (restoreFocus && storeDialogTrigger && storeDialogTrigger.isConnected) storeDialogTrigger.focus();
        }

        document.getElementById('cancelStoreDialog').addEventListener('click', function () { closeStoreDialog(true); });
        document.getElementById('confirmStoreDialog').addEventListener('click', function () {
            const callback = storeDialogCallback;
            closeStoreDialog(false);
            if (callback) callback();
        });
        storeDialog.addEventListener('click', function (event) {
            if (event.target === storeDialog) closeStoreDialog(true);
        });
        document.addEventListener('keydown', function (event) {
            if (!storeDialog.classList.contains('open')) return;
            if (event.key === 'Escape') closeStoreDialog(true);
            else if (event.key === 'Tab') {
                const focusable = Array.from(storeDialog.querySelectorAll('button:not([hidden])'));
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

        init();

        async function init() {
            try {
                const data = await LVApi.get('../api/store/profile.php');
                storeRecord = data.store;
                benefitCatalog = data.benefitCatalog;
                try { LV.renderNav('store', 'dashboard'); LVApi.fixNavLinks(); } catch (navError) { console.warn('Navbar failed to render', navError); }
                document.getElementById('storePortal').hidden = false;
                renderBenefitOptions();
                populateStoreForm();
                renderStoreOverview(data.stats);
                mapPicker = LVMap.picker({
                    mapEl: document.getElementById('storeMap'), address: document.getElementById('editAddress'),
                    findBtn: document.getElementById('findOnMap'), lat: document.getElementById('latitude'),
                    lng: document.getElementById('longitude'), status: document.getElementById('mapStatus'),
                    initial: { lat: storeRecord.latitude, lng: storeRecord.longitude }
                });
                if (storeRecord.status === 'rejected') {
                    lockPage('Your store application was rejected: ' + LV.esc(storeRecord.statusNote || 'Please contact support for details.'));
                } else if (storeRecord.status === 'suspended') {
                    lockPage('Your store account is suspended. Contact LaVanDera support for assistance.');
                }
            } catch (error) {
                document.getElementById('storeLoadMessage').textContent = error.message;
                document.getElementById('storeLoadError').hidden = false;
            }
        }

        // rejected / suspended stores can look but not change anything
        function lockPage(message) {
            const notice = document.getElementById('storeStateMessage');
            notice.hidden = false;
            notice.innerHTML = '<p class="lv-sub">' + message + '</p>';
            document.querySelectorAll('#storeForm input, #storeForm textarea, #storeForm button, #accepting').forEach(function (el) { el.disabled = true; });
        }

        function renderBenefitOptions() {
            document.getElementById('benefitOptions').innerHTML = benefitCatalog.map(function (b) {
                return '<label><input type="checkbox" name="benefits" value="' + LV.esc(b.code) + '"> ' + LV.esc(b.name) +
                    (b.description ? ' <span class="lv-muted">(' + LV.esc(b.description) + ')</span>' : '') + '</label>';
            }).join('');
        }

        function populateStoreForm() {
            const prices = storeRecord.prices || {};
            document.getElementById('storeName').textContent = storeRecord.name;
            document.getElementById('storeDescription').textContent = storeRecord.description || 'Add a description to help customers learn about your store.';
            document.getElementById('storeEmail').textContent = storeRecord.email || '-';
            document.getElementById('storeContact').textContent = storeRecord.contact || '-';
            document.getElementById('storeAddress').textContent = storeRecord.address || '-';
            document.getElementById('storeStatus').innerHTML = LV.userStatusBadge(storeRecord.status);
            document.getElementById('storeBanner').src = storeRecord.banner ? '../' + storeRecord.banner : '../images/lavandera_Background.png';
            document.getElementById('storeImage').src = storeRecord.image ? '../' + storeRecord.image : '../images/Lavandera_logo.png';
            document.getElementById('accepting').checked = !!storeRecord.accepting;
            document.getElementById('editName').value = storeRecord.name || '';
            document.getElementById('editContact').value = storeRecord.contact || '';
            document.getElementById('editAddress').value = storeRecord.address || '';
            document.getElementById('editDescription').value = storeRecord.description || '';
            document.getElementById('priceSmall').value = prices.small || '';
            document.getElementById('priceMedium').value = prices.medium || '';
            document.getElementById('priceLarge').value = prices.large || '';
            document.getElementById('editImage').value = '';
            document.getElementById('editBanner').value = '';
            document.querySelectorAll('input[name="benefits"]').forEach(function (input) {
                input.checked = (storeRecord.benefits || []).indexOf(input.value) > -1;
            });
        }

        function renderStoreOverview(stats) {
            document.getElementById('totalOrders').textContent = stats.total;
            document.getElementById('incomingOrders').textContent = stats.attention;
            document.getElementById('completedOrders').textContent = stats.completed;
        }

        document.getElementById('storeForm').addEventListener('submit', async function (event) {
            event.preventDefault();
            const error = document.getElementById('storeFormError');
            const button = document.getElementById('saveBtn');
            error.textContent = '';

            for (const id of ['priceSmall', 'priceMedium', 'priceLarge']) {
                const price = Number(document.getElementById(id).value);
                if (!Number.isFinite(price) || price <= 0) { openStoreDialog('Check your prices', 'Enter a valid price above zero for every bag size.'); return; }
            }
            if (!document.getElementById('latitude').value || !document.getElementById('longitude').value) {
                openStoreDialog('Pin your store location', 'Press "Find address on map" (or click the map) so delivery fees can be calculated.');
                return;
            }
            for (const id of ['editImage', 'editBanner']) {
                const file = document.getElementById(id).files[0];
                if (file && !file.type.startsWith('image/')) { openStoreDialog('Check your images', 'Choose a valid image file.'); return; }
                if (file && file.size > MAX_IMAGE_BYTES) { openStoreDialog('Check your images', 'Each image must be 2 MB or smaller.'); return; }
            }

            const body = new FormData();
            body.append('store_name', document.getElementById('editName').value.trim());
            body.append('contact', document.getElementById('editContact').value.trim());
            body.append('address', document.getElementById('editAddress').value.trim());
            body.append('description', document.getElementById('editDescription').value.trim());
            body.append('latitude', document.getElementById('latitude').value);
            body.append('longitude', document.getElementById('longitude').value);
            body.append('accepting', document.getElementById('accepting').checked ? '1' : '0');
            body.append('price_small', document.getElementById('priceSmall').value);
            body.append('price_medium', document.getElementById('priceMedium').value);
            body.append('price_large', document.getElementById('priceLarge').value);
            document.querySelectorAll('input[name="benefits"]:checked').forEach(function (input) { body.append('benefits[]', input.value); });
            const image = document.getElementById('editImage').files[0];
            const banner = document.getElementById('editBanner').files[0];
            if (image) body.append('image', image);
            if (banner) body.append('banner', banner);

            button.disabled = true;
            try {
                const data = await LVApi.postForm('../api/store/profile.php', body);
                storeRecord = data.store;
                populateStoreForm();
                renderStoreOverview(data.stats);
                openStoreDialog('Changes saved', 'Your store information and order settings have been updated.');
            } catch (saveError) {
                openStoreDialog('Unable to save changes', saveError.message || 'Unable to save store changes.');
            }
            button.disabled = false;
        });
    </script>
</body>
</html>
