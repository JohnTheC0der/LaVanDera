<?php
/**
 * Customer Place Order page (converted to PHP).
 * Runs on the SERVER before any HTML is sent:
 *   1. bootstrap.php = config + session + helpers
 *   2. guard_customer_page() = redirects away unless the visitor is the right, approved kind of user
 *   3. $lvConfig / $lvSession = handed to JavaScript below (CSRF token + who is really signed in)
 */
require __DIR__ . '/../../src/bootstrap.php';
require_once LV_ROOT . '/src/auth/guards.php';
$user = guard_customer_page();
$lvConfig  = ['loginUrl' => config('login_url'), 'logoutUrl' => '../auth/logout.php', 'csrf' => csrf_token()];
$lvSession = ['id' => $user['id'], 'role' => 'customer', 'name' => $user['name'], 'email' => $user['email']];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <style>
        /* page-only: heading block above each step */
        .lv-step-head { margin-bottom: 14px; }
        .lv-step-head h2 { font-size: 20px; color: var(--lv-plum); }
        .lv-step-head p { color: var(--lv-muted); font-size: 13.5px; margin-top: 3px; }
    </style>
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">

        <!-- PAGE HEAD -->
        <section class="lv-page-head">
            <div class="lv-eyebrow">Three quick steps</div>
            <h1 class="lv-title">Place an Order</h1>
            <p class="lv-sub">Select a store &rarr; pick a payment option (Cash or GCash) &rarr; choose your bag,
                then review everything before we send it to the shop.</p>
        </section>

        <!-- STEPPER -->
        <div class="lv-steps" id="stepper"></div>

        <p class="lv-error" id="placeError" role="alert"></p>

        <!-- STEP BODY -->
        <div id="stepBody"></div>

    </main>

    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
    <script src="../js/lavandera_links.js"></script>
    <script>
        // The SERVER already checked who you are (guard_customer_page in the PHP block at the top).
        // window.LV_SESSION is the real signed-in user, replacing the old demo profile (Maria).
        const me = window.LV_SESSION;
        try { LV.renderNav('customer', 'place'); LVApi.fixNavLinks(); LVLinks.fix(); } catch (navError) { console.warn('Navbar failed to render', navError); }

        // Images are saved in the database as paths relative to public/ (e.g. uploads/stores/x.jpg); pages live one folder deeper.
        const imgUrl = function (path) { return path ? '../' + path : ''; };
        const IMG_FALLBACK = "this.onerror=null; this.className='lv-media lv-banner lv-fallback'; this.removeAttribute('src');";

        const STEP_LABELS = ['Store', 'Payment', 'Bag'];
        const PAYMENTS = [
            { key: 'cash',  icon: 'fa-money-bill-wave',      title: 'Cash',  sub: 'Pay the rider or the store on delivery' },
            { key: 'gcash', icon: 'fa-mobile-screen-button', title: 'GCash', sub: 'Pay online now - reference number issued' }
        ];
        let step = 1;
        let storeId = null;
        let payment = null;
        let bag = null;
        let notes = '';
        let stores = [];
        let feeRule = { base: 40, baseKm: 2, perKm: 10 };

        function availableStores() {
            return stores.filter(function (s) { return s.accepting; });
        }
        function storeById(id) {
            return stores.filter(function (s) { return s.id === id; })[0] || null;
        }

        async function init() {
            try {
                const data = await LVApi.get('../api/customer/stores.php');   // stores WITH this customer's distance + fee
                stores = data.stores;
                feeRule = data.feeRule;
            } catch (error) {
                document.getElementById('placeError').textContent = error.message;
                return;
            }
            const list = availableStores();
            if (!list.length) {
                document.getElementById('stepper').style.display = 'none';
                document.getElementById('stepBody').innerHTML =
                    '<div class="lv-card lv-empty"><i class="fa-solid fa-store-slash"></i><h3>No stores available right now</h3>' +
                    '<p>Partner laundromats are joining every day - check back soon.</p>' +
                    '<a href="Customer_Home_Page.php" class="lv-btn lv-btn-primary" style="margin-top:16px;">Back to Home</a></div>';
                return;
            }
            // deep link ?store=ID preselects a store and jumps to the payment step
            const q = Number(new URLSearchParams(location.search).get('store'));
            if (q && list.some(function (s) { return s.id === q; })) { storeId = q; step = 2; }
            render();
        }
        init();

        function render() {
            document.getElementById('stepper').innerHTML = stepper();
            const host = document.getElementById('stepBody');
            if (step === 1) host.innerHTML = stepStore();
            else if (step === 2) host.innerHTML = stepPayment();
            else host.innerHTML = stepBag();
            const t = document.getElementById('orderNotes');
            if (t) { t.value = notes; t.addEventListener('input', function () { notes = t.value; }); }
        }

        function stepper() {
            return STEP_LABELS.map(function (label, i) {
                const n = i + 1;
                const cls = n < step ? 'done' : n === step ? 'active' : '';
                const inner = n < step ? '<i class="fa-solid fa-check"></i>' : n;
                return (i ? '<div class="lv-step-line"></div>' : '') +
                    '<div class="lv-step ' + cls + '"><span class="lv-step-num">' + inner + '</span>' + label + '</div>';
            }).join('');
        }

        function stepHead(num, title, sub) {
            return '<div class="lv-step-head"><div class="lv-eyebrow">Step ' + num + '</div><h2>' + title + '</h2><p>' + sub + '</p></div>';
        }

        function progressLine() {
            const s = storeById(storeId);
            if (!s) return '';
            return '<div class="lv-muted" style="font-size:13px; margin-bottom:14px;">' +
                '<i class="fa-solid fa-store" style="color:var(--lv-pink);"></i> Continuing with <strong style="color:var(--lv-plum);">' +
                LV.esc(s.name) + '</strong></div>';
        }

        function goStep(n) { step = n; render(); }

        /* ---- step 1: store ---- */
        function stepStore() {
            return stepHead(1, 'Choose a store', 'Only shops that are accepting new orders are listed.') +
                '<div class="lv-grid lv-grid-3">' + availableStores().map(storeOption).join('') + '</div>' +
                '<div class="lv-row" style="justify-content:flex-end; margin-top:22px;">' +
                    '<button type="button" class="lv-btn lv-btn-primary" onclick="goStep(2)"' + (storeId ? '' : ' disabled') + '>Continue <i class="fa-solid fa-arrow-right"></i></button>' +
                '</div>';
        }

        function storeOption(s) {
            const hasKm = s.distanceKm != null;
            const feeText = !hasKm ? 'pin your address in My Profile for the fee' : s.freeDelivery ? 'free delivery' : 'est. rider fee ' + LV.peso(s.deliveryFee) + ' each way';
            return '<div class="lv-option' + (storeId === s.id ? ' selected' : '') + '" onclick="pickStore(' + s.id + ')">' +
                '<span class="lv-check"><i class="fa-solid fa-check"></i></span>' +
                '<div class="lv-option-title">' + LV.esc(s.name) + '</div>' +
                '<div class="lv-option-sub">' + LV.esc(s.address || 'Address to be confirmed') + '</div>' +
                '<div class="lv-option-sub" style="margin-top:9px;"><i class="fa-solid fa-location-dot" style="color:var(--lv-pink);"></i> ' +
                    (hasKm ? s.distanceKm.toFixed(1) + ' km away' : 'Distance to confirm') + ' &middot; ' + feeText + '</div>' +
                '<div class="lv-option-sub" style="margin-top:3px;">Small ' + LV.peso(s.prices.small) +
                    ' &middot; Medium ' + LV.peso(s.prices.medium) + ' &middot; Large ' + LV.peso(s.prices.large) + '</div></div>';
        }

        function pickStore(id) { storeId = id; bag = null; render(); }

        /* ---- step 2: payment ---- */
        function stepPayment() {
            const cards = PAYMENTS.map(function (p) {
                return '<div class="lv-option' + (payment === p.key ? ' selected' : '') + '" onclick="pickPayment(\'' + p.key + '\')">' +
                    '<span class="lv-check"><i class="fa-solid fa-check"></i></span>' +
                    '<div class="lv-option-title"><i class="fa-solid ' + p.icon + '" style="color:var(--lv-pink);"></i> ' + p.title + '</div>' +
                    '<div class="lv-option-sub" style="margin-top:4px;">' + p.sub + '</div></div>';
            }).join('');
            return stepHead(2, 'Payment method', 'How would you like to settle the bill?') + progressLine() +
                '<div class="lv-grid lv-grid-2">' + cards + '</div>' +
                '<div class="lv-row-between" style="margin-top:22px;">' +
                    '<button type="button" class="lv-btn lv-btn-outline" onclick="goStep(1)"><i class="fa-solid fa-arrow-left"></i> Back</button>' +
                    '<button type="button" class="lv-btn lv-btn-primary" onclick="goStep(3)"' + (payment ? '' : ' disabled') + '>Continue <i class="fa-solid fa-arrow-right"></i></button>' +
                '</div>';
        }

        function pickPayment(key) { payment = key; render(); }

        /* ---- step 3: bag ---- */
        // This is only a PREVIEW. The server recalculates every price again when the order is really placed.
        function computeFees() {
            const store = storeById(storeId);
            const free = !!store.freeDelivery;
            const leg = free ? 0 : (store.deliveryFee || 0);
            const storeFee = bag ? store.prices[bag] : 0;
            return { store: store, free: free, distance: store.distanceKm, storeFee: storeFee, send: leg, ret: leg, total: storeFee + leg + leg };
        }

        function stepBag() {
            const f = computeFees();
            const cards = LV.bags.map(function (b) {
                return '<div class="lv-option' + (bag === b.code ? ' selected' : '') + '" onclick="pickBag(\'' + b.code + '\')">' +
                    '<span class="lv-check"><i class="fa-solid fa-check"></i></span>' +
                    '<div class="lv-option-title">' + LV.esc(b.name) + '</div>' +
                    '<div class="lv-option-sub">Up to ' + b.kg + ' KG</div>' +
                    '<div class="lv-option-price" style="margin-top:8px;">' + LV.peso(f.store.prices[b.code]) + '</div></div>';
            }).join('');
            const needPin = f.distance == null;
            return stepHead(3, 'Choose your bag', 'Pick the bag size and add any special instructions.') + progressLine() +
                (needPin ? '<div class="lv-tone-amber" style="border-radius:14px; padding:12px 16px; margin-bottom:14px; font-size:14px;">' +
                    '<i class="fa-solid fa-triangle-exclamation"></i> We need your map location to work out the delivery fee. ' +
                    '<a class="lv-link" href="Customer_Profile_Page.php">Pin your address in My Profile</a>, then come back.</div>' : '') +
                '<div class="lv-grid lv-grid-3">' + cards + '</div>' +
                '<div class="lv-field" style="margin-top:20px;">' +
                    '<label class="lv-label" for="orderNotes">Notes (optional)</label>' +
                    '<textarea class="lv-textarea" id="orderNotes" maxlength="500" placeholder="Special instructions for the rider or store..."></textarea></div>' +
                preview(f) +
                '<div class="lv-row-between" style="margin-top:22px;">' +
                    '<button type="button" class="lv-btn lv-btn-outline" onclick="goStep(2)"><i class="fa-solid fa-arrow-left"></i> Back</button>' +
                    '<button type="button" class="lv-btn lv-btn-pink" onclick="reviewOrder()"' + (bag && !needPin ? '' : ' disabled') + '>Review Order <i class="fa-solid fa-arrow-right"></i></button>' +
                '</div>';
        }

        function preview(f) {
            const storeRow = bag ? LV.peso(f.storeFee) : '<span class="lv-muted" style="font-weight:600;">Select a bag</span>';
            const freeTag = '<span style="color:var(--lv-green); font-weight:800;">FREE</span>';
            const total = bag && f.distance != null ? '<span class="lv-total">' + LV.peso(f.total) + '</span>' : '<span class="lv-muted">&mdash;</span>';
            return '<div class="lv-card lv-card-pad" style="margin-top:20px;">' +
                '<div class="lv-eyebrow">Live preview</div>' +
                '<h3 style="font-size:17px; color:var(--lv-plum); margin-bottom:6px;">Order summary</h3>' +
                '<div class="lv-kv"><span>Store fee</span><span>' + storeRow + '</span></div>' +
                '<div class="lv-kv"><span>Send fee (you &rarr; store' + (f.distance != null ? ', ' + f.distance.toFixed(1) + ' km' : '') + ')</span><span>' + (f.free ? freeTag : (f.distance != null ? LV.peso(f.send) : '&mdash;')) + '</span></div>' +
                '<div class="lv-kv"><span>Return fee (store &rarr; you)</span><span>' + (f.free ? freeTag : (f.distance != null ? LV.peso(f.ret) : '&mdash;')) + '</span></div>' +
                '<div class="lv-kv" style="border-bottom:none;"><span style="color:var(--lv-plum); font-weight:800;">Total</span><span>' + total + '</span></div>' +
                '<p class="lv-muted" style="font-size:12.5px; margin-top:8px;"><i class="fa-solid fa-circle-info" style="color:var(--lv-pink);"></i> ' +
                    (f.free ? 'This store includes free pickup and delivery.' :
                     'Rider fees are distance-based: ' + LV.peso(feeRule.base) + ' for the first ' + feeRule.baseKm + ' km + ' + LV.peso(feeRule.perKm) + ' per extra km.') +
                '</p></div>';
        }

        function pickBag(code) {
            const t = document.getElementById('orderNotes');
            if (t) notes = t.value;
            bag = code;
            render();
        }

        function reviewOrder() {
            const t = document.getElementById('orderNotes');
            if (t) notes = t.value;
            if (!storeId || !payment || !bag) { LV.toast('Please finish every step first.', 'fa-circle-exclamation'); return; }
            // The draft only lives in this browser tab until the customer confirms on the next page.
            sessionStorage.setItem('lv_order_draft', JSON.stringify({ storeId: storeId, payment: payment, bag: bag, notes: notes }));
            location.href = 'Customer_Confirm_Order.php';
        }
    </script>
</body>

</html>
