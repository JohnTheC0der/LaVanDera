<?php
/**
 * Customer My Orders page (converted to PHP).
 * The <?php ... ?> block runs on the SERVER before any HTML is sent:
 *   1. bootstrap.php  = config + session + helpers (same line every page starts with)
 *   2. guard_...()    = redirect away unless the visitor is the right, approved kind of user
 *   3. $lvConfig / $lvSession = values handed to JavaScript further down (CSRF token + who is signed in)
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
    <title>My Orders - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <style>
        /* page-only: status flow timeline + collapsible history */
        .lv-flow { display: flex; flex-wrap: wrap; align-items: center; gap: 9px 4px; margin-top: 16px; padding-top: 14px; border-top: 1px dashed var(--lv-line); }
        .lv-flow-step { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 700; color: #cfa9bd; }
        .lv-flow-step.done { color: var(--lv-muted); }
        .lv-flow-step.now { color: var(--lv-plum); font-weight: 800; }
        .lv-flow-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--lv-line); flex-shrink: 0; }
        .lv-flow-step.done .lv-flow-dot { background: var(--lv-plum); }
        .lv-dot-now { background: var(--lv-pink) !important; animation-name: lv-pulse-pink; }
        .lv-flow-line { width: 16px; height: 1.5px; background: var(--lv-line); flex-shrink: 0; }
        @keyframes lv-pulse-pink { 70% { box-shadow: 0 0 0 9px rgba(236, 72, 153, 0); } 100% { box-shadow: 0 0 0 0 rgba(236, 72, 153, 0); } }
        .lv-hist { margin-top: 16px; padding-top: 12px; border-top: 1px dashed var(--lv-line); }
        .lv-hist summary { cursor: pointer; list-style: none; font-size: 12.5px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--lv-plum-soft); display: flex; align-items: center; gap: 8px; }
        .lv-hist summary::-webkit-details-marker { display: none; }
        .lv-hist summary i { color: var(--lv-pink); font-size: 12px; }
        .lv-hist-row { display: flex; justify-content: space-between; gap: 14px; padding: 7px 0; border-bottom: 1px dashed var(--lv-line); font-size: 13.5px; }
        .lv-hist-row:last-child { border-bottom: none; }
        .lv-hist-row b { color: var(--lv-plum); font-weight: 700; }
    </style>
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">

        <!-- PAGE HEAD -->
        <section class="lv-page-head">
            <div class="lv-eyebrow">Order tracking</div>
            <h1 class="lv-title">My Orders</h1>
            <p class="lv-sub">Follow every bag through the ride: Waiting for driver &rarr; Enroute to store &rarr;
                At laundromat &rarr; Enroute to you. Tap a filter to focus on what matters.</p>
        </section>

        <!-- FILTER TABS -->
        <div class="lv-wrap" id="orderFilters" style="margin-bottom:18px;"></div>

        <p class="lv-error" id="ordersError" role="alert"></p>

        <!-- ORDER LIST -->
        <div id="orderList" style="display:flex; flex-direction:column; gap:16px;"></div>

    </main>

    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
    <script>
        const PROGRESS = ['pending_store', 'waiting_for_driver', 'enroute_to_store', 'at_laundromat',
                          'waiting_for_return_driver', 'enroute_to_customer'];
        const TABS = [
            { key: 'all',       label: 'All' },
            { key: 'progress',  label: 'In progress' },
            { key: 'completed', label: 'Completed' },
            { key: 'closed',    label: 'Cancelled/Rejected' }
        ];
        let filter = 'all';
        let scrolledOnce = false;
        const deepLink = new URLSearchParams(location.search).get('order');

        // Before: LV.requireRole() + LV.ordersOfCustomer() read browser storage.
        // Now: the server already checked who we are (guard_customer_page above); we only fetch the orders.
        let orders = [];
        try { LV.renderNav('customer', 'orders'); LVApi.fixNavLinks(); } catch (navError) { console.warn('Navbar failed to render', navError); }

        async function loadOrders() {
            try {
                orders = (await LVApi.get('../api/customer/orders.php')).orders;
                document.getElementById('ordersError').textContent = '';
                render();
            } catch (error) {
                document.getElementById('ordersError').textContent = error.message;
            }
        }
        loadOrders();

        function isProgress(o) { return PROGRESS.indexOf(o.status) > -1; }

        function matchFilter(o) {
            if (filter === 'progress') return isProgress(o);
            if (filter === 'completed') return o.status === 'completed';
            if (filter === 'closed') return o.status === 'cancelled' || o.status === 'rejected';
            return true;
        }

        function setFilter(key) { filter = key; render(); }

        function countFor(key, mine) {
            if (key === 'all') return mine.length;
            if (key === 'progress') return mine.filter(isProgress).length;
            if (key === 'completed') return mine.filter(function (o) { return o.status === 'completed'; }).length;
            return mine.filter(function (o) { return o.status === 'cancelled' || o.status === 'rejected'; }).length;
        }

        function render() {
            const mine = orders;      // was: LV.ordersOfCustomer(me.id)

            /* ---- filter pills ---- */
            document.getElementById('orderFilters').innerHTML = TABS.map(function (t) {
                return '<button type="button" class="lv-btn ' + (filter === t.key ? 'lv-btn-primary' : 'lv-btn-outline') +
                    ' lv-btn-sm" onclick="setFilter(\'' + t.key + '\')">' + t.label + ' (' + countFor(t.key, mine) + ')</button>';
            }).join('');

            /* ---- list ---- */
            const list = mine.filter(matchFilter);
            const host = document.getElementById('orderList');
            if (!mine.length) {
                host.innerHTML =
                    '<div class="lv-card lv-empty"><i class="fa-solid fa-basket-shopping"></i>' +
                    '<h3>No orders yet</h3><p>Place your first laundry order and track every step right here.</p>' +
                    '<a href="Customer_Place_Order_Page.html" class="lv-btn lv-btn-primary" style="margin-top:16px;">' +
                    '<i class="fa-solid fa-plus"></i> Place an Order</a></div>';
            } else if (!list.length) {
                host.innerHTML =
                    '<div class="lv-card lv-empty"><i class="fa-solid fa-magnifying-glass"></i>' +
                    '<h3>Nothing in this tab</h3><p>No orders match this filter right now.</p></div>';
            } else {
                host.innerHTML = list.map(orderCard).join('');
            }

            if (deepLink && !scrolledOnce) { scrolledOnce = true; highlight(deepLink); }
        }

        function highlight(id) {
            const el = document.querySelector('[data-order="' + String(id).replace(/[^0-9]/g, '') + '"]');
            if (!el) return;
            el.style.outline = '2px solid var(--lv-pink)';
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        /* ------------------------------------------------- order card -- */
        function orderCard(o) {
            const bag = LV.bagOf(o.bag);   // LV.bagOf is still a pure helper (icon + kg for a bag code)
            const pay = o.payment === 'gcash' ? 'GCASH' : 'CASH';

            return '<article class="lv-card lv-card-pad" data-order="' + o.id + '">' +
                '<div class="lv-row-between" style="align-items:flex-start;">' +
                    '<div class="lv-row" style="align-items:flex-start;">' +
                        '<div class="lv-avatar"><i class="fa-solid ' + bag.icon + '" style="font-size:18px;"></i></div>' +
                        '<div>' +
                            '<div style="font-weight:800; color:var(--lv-plum); font-size:16px;">' +
                                LV.esc(o.storeName || 'Store') + '</div>' +
                            '<div class="lv-muted" style="font-size:13px;">#' + o.id + ' &middot; ' + bag.name + ' (' + bag.kg +
                                'kg) &middot; ' + pay + ' &middot; placed ' + LV.when(o.createdAt) + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="lv-row" style="gap:14px; flex-shrink:0;">' +
                        '<span style="font-weight:800; font-size:16px;">' + LV.peso(o.total) + '</span>' +
                        LV.statusBadge(o.status) +
                    '</div>' +
                '</div>' +
                timeline(o) +
                feeRow(o) +
                historyBlock(o) +
                actions(o) +
            '</article>';
        }

        /* progress timeline (or rejection note for statuses outside FLOW) */
        function timeline(o) {
            const idx = LV.FLOW.indexOf(o.status);

            if (idx === -1) {
                const reason = o.storeRejectionReason
                    ? '<div class="lv-tone-red" style="border-radius:12px; padding:10px 14px; font-size:13.5px; margin-top:10px; display:flex; gap:9px; align-items:flex-start;">' +
                        '<i class="fa-solid fa-circle-info" style="margin-top:3px;"></i>' +
                        '<span><strong>Reason:</strong> ' + LV.esc(o.storeRejectionReason) + '</span></div>'
                    : '';
                return '<div style="margin-top:16px; padding-top:14px; border-top:1px dashed var(--lv-line);">' +
                    LV.statusBadge(o.status) + reason + '</div>';
            }

            const steps = LV.FLOW.map(function (code, i) {
                const state = i < idx ? ' done' : i === idx ? ' now' : '';
                const dot = '<span class="lv-flow-dot' + (i === idx ? ' lv-pulse lv-dot-now' : '') + '"></span>';
                return (i ? '<span class="lv-flow-line"></span>' : '') +
                    '<span class="lv-flow-step' + state + '">' + dot + LV.STATUS[code].label + '</span>';
            }).join('');

            return '<div class="lv-flow">' + steps + '</div>';
        }

        /* store fee / send fee / return fee / total */
        function feeRow(o) {
            const free = !!o.freeDelivery;
            const send = free ? '<span style="color:var(--lv-green); font-weight:800;">FREE</span>' : LV.peso(o.sendFee);
            const ret = free ? '<span style="color:var(--lv-green); font-weight:800;">FREE</span>' : LV.peso(o.returnFee);
            return '<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(135px,1fr)); gap:0 20px; margin-top:16px; padding-top:4px; border-top:1px solid var(--lv-line);">' +
                '<div class="lv-kv"><span>Store fee</span><span>' + LV.peso(o.storeFee) + '</span></div>' +
                '<div class="lv-kv"><span>Send fee</span><span>' + send + '</span></div>' +
                '<div class="lv-kv"><span>Return fee</span><span>' + ret + '</span></div>' +
                '<div class="lv-kv"><span>Total</span><span style="color:var(--lv-pink) !important;">' + LV.peso(o.total) + '</span></div>' +
            '</div>';
        }

        /* collapsible status history */
        function historyBlock(o) {
            const rows = (o.history || []).slice().reverse().map(function (h) {
                const st = LV.STATUS[h.status] || { label: h.status };
                return '<div class="lv-hist-row"><b>' + LV.esc(st.label) + '</b>' +
                    '<span class="lv-muted">' + (h.note ? LV.esc(h.note) + ' &middot; ' : '') + LV.when(h.at) + '</span></div>';
            }).join('');
            return '<details class="lv-hist"><summary><i class="fa-solid fa-clock-rotate-left"></i> Status history (' +
                (o.history || []).length + ')</summary><div style="margin-top:8px;">' + rows + '</div></details>';
        }

        /* per-status actions */
        function actions(o) {
            let html = '';
            if (o.status === 'enroute_to_customer') {
                html += '<button type="button" class="lv-btn lv-btn-pink lv-btn-sm" onclick="confirmReturn(' + o.id + ')">' +
                    '<i class="fa-solid fa-circle-check"></i> Confirm Return</button>';
            }
            if (o.status === 'pending_store') {
                html += '<button type="button" class="lv-btn lv-btn-danger lv-btn-sm" onclick="cancelOrder(' + o.id + ')">' +
                    '<i class="fa-solid fa-ban"></i> Cancel Order</button>';
            }
            return html ? '<div class="lv-wrap" style="margin-top:16px;">' + html + '</div>' : '';
        }

        // One function for both buttons: POST the action, the reply is the refreshed list.
        async function runOrderAction(id, action) {
            try {
                const result = await LVApi.postJson('../api/customer/orders.php', { action: action, order_id: id });
                orders = result.orders;
                document.getElementById('ordersError').textContent = '';
                render();
                return true;
            } catch (error) {
                document.getElementById('ordersError').textContent = error.message;
                loadOrders();      // the order may have changed on the server: reload the truth
                return false;
            }
        }

        async function confirmReturn(id) {
            if (await runOrderAction(id, 'confirm_return')) LV.toast('Order #' + id + ' completed. Enjoy your clean laundry!');
        }

        async function cancelOrder(id) {
            if (!confirm('Cancel order #' + id + '? This cannot be undone.')) return;
            if (await runOrderAction(id, 'cancel')) LV.toast('Order #' + id + ' cancelled.');
        }
    </script>
</body>

</html>
