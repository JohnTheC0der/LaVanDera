<?php
/**
 * Customer Confirm Order page (converted to PHP).
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
    <title>Confirm Order - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <style>
        /* page-only: fee footnote under the breakdown */
        .lv-fee-note { font-size: 12.5px; color: var(--lv-muted); margin-top: 12px; }
        .lv-fee-note i { color: var(--lv-pink); }
    </style>
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">

        <!-- PAGE HEAD -->
        <section class="lv-page-head">
            <div class="lv-eyebrow">Almost there</div>
            <h1 class="lv-title">Confirm Your Order</h1>
            <p class="lv-sub">One last look at the fees and details. Once confirmed, your order is sent to the store
                for approval and a rider is dispatched.</p>
        </section>

        <!-- STEPPER: 1-3 done, Confirm active -->
        <div class="lv-steps">
            <div class="lv-step done"><span class="lv-step-num"><i class="fa-solid fa-check"></i></span>Store</div>
            <div class="lv-step-line"></div>
            <div class="lv-step done"><span class="lv-step-num"><i class="fa-solid fa-check"></i></span>Payment</div>
            <div class="lv-step-line"></div>
            <div class="lv-step done"><span class="lv-step-num"><i class="fa-solid fa-check"></i></span>Bag</div>
            <div class="lv-step-line"></div>
            <div class="lv-step active"><span class="lv-step-num">4</span>Confirm</div>
        </div>

        <!-- SUMMARY + BREAKDOWN -->
        <div class="lv-grid lv-grid-2" id="confirmGrid"></div>

        <p class="lv-error" id="confirmError" role="alert"></p>

        <!-- ACTIONS -->
        <div id="confirmActions"></div>

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

        const BAG_CODES = LV.bags.map(function (b) { return b.code; });
        let stores = [];
        let feeRule = { base: 40, baseKm: 2, perKm: 10 };
        let customer = null;
        let draft = null;
        let placing = false;       // true while the order is being sent (stops double clicks)

        function readDraft() {
            let d = null;
            try { d = JSON.parse(sessionStorage.getItem('lv_order_draft')); } catch (e) { d = null; }
            if (!d || !d.storeId) return null;
            if (d.payment !== 'cash' && d.payment !== 'gcash') return null;
            if (BAG_CODES.indexOf(d.bag) === -1) return null;
            return { storeId: Number(d.storeId), payment: d.payment, bag: d.bag, notes: String(d.notes || '') };
        }

        async function boot() {
            draft = readDraft();
            if (!draft) { location.replace('Customer_Place_Order_Page.php'); return; }
            try {
                const replies = await Promise.all([LVApi.get('../api/customer/stores.php'), LVApi.get('../api/customer/profile.php')]);
                stores = replies[0].stores;
                feeRule = replies[0].feeRule;
                customer = replies[1].customer;
            } catch (error) {
                document.getElementById('confirmError').textContent = error.message;
                return;
            }
            if (!stores.some(function (s) { return s.id === draft.storeId; })) { location.replace('Customer_Place_Order_Page.php'); return; }
            render();
        }
        boot();

        // PREVIEW only: the server works the real numbers out again when you press Confirm.
        function computeFees() {
            const store = stores.filter(function (s) { return s.id === draft.storeId; })[0];
            const free = !!store.freeDelivery;
            const leg = free ? 0 : (store.deliveryFee || 0);
            const storeFee = store.prices[draft.bag];
            return { store: store, free: free, distance: store.distanceKm, storeFee: storeFee, send: leg, ret: leg, total: storeFee + leg + leg };
        }

        function render() {
            const f = computeFees();
            const bagInfo = LV.bagOf(draft.bag);
            const gcash = draft.payment === 'gcash';
            const freeTag = '<span style="color:var(--lv-green); font-weight:800;">FREE</span>';

            const summary =
                '<div class="lv-card lv-card-pad">' +
                    '<div class="lv-row-between" style="margin-bottom:16px;"><h2 class="lv-section-title">Order summary</h2>' +
                    '<span class="lv-badge lv-tone-pink"><i class="fa-solid fa-receipt"></i>Review</span></div>' +
                    '<div class="lv-row" style="align-items:flex-start;">' +
                        '<div class="lv-avatar"><i class="fa-solid ' + bagInfo.icon + '" style="font-size:18px;"></i></div>' +
                        '<div><div style="font-weight:800; color:var(--lv-plum); font-size:16px;">' + LV.esc(f.store.name) + '</div>' +
                        '<div class="lv-muted" style="font-size:13px;">' + LV.esc(f.store.address || 'Address to be confirmed') + '</div>' +
                        '<div class="lv-muted" style="font-size:13px; margin-top:2px;"><i class="fa-solid fa-route" style="color:var(--lv-pink);"></i> ' +
                            (f.distance != null ? f.distance.toFixed(1) + ' km from you' : 'distance unknown') + '</div></div>' +
                    '</div>' +
                    '<hr class="lv-divider">' +
                    '<div class="lv-label">Pickup &amp; drop-off</div>' +
                    '<div style="font-size:14.5px;"><i class="fa-solid fa-location-dot" style="color:var(--lv-pink);"></i> ' +
                        LV.esc(customer ? customer.address : 'Address to be confirmed') + '</div>' +
                    '<div class="lv-option selected" style="cursor:default; margin-top:16px;"><div class="lv-row">' +
                        '<div class="lv-avatar lv-avatar-sm"><i class="fa-solid ' + bagInfo.icon + '" style="font-size:15px;"></i></div>' +
                        '<div><div class="lv-option-title">' + LV.esc(bagInfo.name) + '</div><div class="lv-option-sub">Up to ' + bagInfo.kg + ' KG</div></div>' +
                        '<div class="lv-option-price" style="margin-left:auto;">' + LV.peso(f.storeFee) + '</div></div></div>' +
                    '<div class="lv-row-between" style="margin-top:16px;"><span class="lv-muted" style="font-size:13px;">Payment method</span>' +
                        (gcash ? '<span class="lv-badge lv-tone-blue"><i class="fa-solid fa-mobile-screen"></i>GCash</span>'
                               : '<span class="lv-badge lv-tone-green"><i class="fa-solid fa-money-bill-wave"></i>Cash</span>') + '</div>' +
                    (draft.notes ? '<hr class="lv-divider"><div class="lv-label">Your notes</div><div style="font-size:14px; color:var(--lv-ink);">' +
                        '<i class="fa-solid fa-quote-left" style="color:var(--lv-pink-soft);"></i> ' + LV.esc(draft.notes) + '</div>' : '') +
                '</div>';

            const breakdown =
                '<div class="lv-card lv-card-pad">' +
                    '<div class="lv-row-between" style="margin-bottom:10px;"><h2 class="lv-section-title">Price breakdown</h2>' +
                        (f.free ? '<span class="lv-badge lv-tone-green"><i class="fa-solid fa-truck-fast"></i>Free delivery</span>' : '') + '</div>' +
                    '<div class="lv-kv"><span>Store fee &middot; ' + LV.esc(bagInfo.name) + '</span><span>' + LV.peso(f.storeFee) + '</span></div>' +
                    '<div class="lv-kv"><span>Send fee (you &rarr; store)</span><span>' + (f.free ? freeTag : LV.peso(f.send)) + '</span></div>' +
                    '<div class="lv-kv"><span>Return fee (store &rarr; you)</span><span>' + (f.free ? freeTag : LV.peso(f.ret)) + '</span></div>' +
                    '<hr class="lv-divider">' +
                    '<div class="lv-kv" style="border-bottom:none; padding-top:4px;"><span style="color:var(--lv-plum); font-weight:800; font-size:15px;">Total</span>' +
                        '<span class="lv-total">' + LV.peso(f.total) + '</span></div>' +
                    '<p class="lv-fee-note"><i class="fa-solid fa-circle-info"></i> Rider fees are distance-based: ' + LV.peso(feeRule.base) +
                        ' for the first ' + feeRule.baseKm + ' km + ' + LV.peso(feeRule.perKm) + ' per extra km.</p>' +
                '</div>';

            document.getElementById('confirmGrid').innerHTML = summary + breakdown;

            const blocked = f.distance == null;      // no map pin yet: the server would refuse the order
            document.getElementById('confirmActions').innerHTML =
                (blocked ? '<p class="lv-error" style="display:block;">Pin your address in <a class="lv-link" href="Customer_Profile_Page.php">My Profile</a> first, so the delivery fee can be worked out.</p>' : '') +
                '<div class="lv-row-between lv-mt" style="flex-wrap:wrap;">' +
                    '<button type="button" class="lv-btn lv-btn-outline" onclick="backToEdit()"><i class="fa-solid fa-arrow-left"></i> Back to editing</button>' +
                    '<div style="text-align:right;"><button type="button" class="lv-btn lv-btn-pink" id="confirmBtn" onclick="confirmOrder()"' + (blocked ? ' disabled' : '') + '>' +
                        '<i class="fa-solid fa-paper-plane"></i> Confirm Order</button>' +
                        '<div class="lv-muted" style="font-size:12.5px; margin-top:8px;">The store will confirm your order shortly. Track it in My Orders.</div></div>' +
                '</div>';
        }

        function backToEdit() {
            if (history.length > 1) history.back();
            else location.href = 'Customer_Place_Order_Page.php';
        }

        async function confirmOrder() {
            if (placing) return;
            placing = true;
            document.getElementById('confirmError').textContent = '';
            const button = document.getElementById('confirmBtn');
            if (button) button.disabled = true;
            try {
                // THE moment the order is saved: the server checks the store is still open and recalculates every fee.
                const result = await LVApi.postJson('../api/customer/orders.php', {
                    action: 'place', store_id: draft.storeId, bag: draft.bag, payment: draft.payment, notes: draft.notes
                });
                sessionStorage.removeItem('lv_order_draft');
                LV.toast('Order sent to the store for confirmation!');
                location.href = 'Customer_My_Orders_Page.php?order=' + result.orderId;
            } catch (error) {
                document.getElementById('confirmError').textContent = error.message;   // e.g. "This store is not accepting orders right now."
                placing = false;
                if (button) button.disabled = false;
            }
        }
    </script>
</body>

</html>
