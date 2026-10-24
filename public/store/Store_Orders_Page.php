<?php
require __DIR__ . '/../../src/bootstrap.php';
$user = guard_store_page('portal');

$blocked = null;                       // rejected / suspended stores see a notice instead of the portal
if ($user['status'] === 'rejected') {
    $blocked = 'Your store application was rejected. Contact support for assistance.';
} elseif ($user['status'] === 'suspended') {
    $blocked = 'Your store account is suspended. Contact support for assistance.';
}
$lvConfig  = ['loginUrl' => config('login_url'), 'logoutUrl' => '../auth/logout.php', 'csrf' => csrf_token()];
$lvSession = ['id' => $user['id'], 'role' => 'store', 'name' => $user['name'], 'email' => $user['email']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Orders - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <style>
        .store-order-list { display: grid; gap: 16px; margin-bottom: 30px; }
        .store-order-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
        .store-order-actions { display: flex; gap: 9px; flex-wrap: wrap; margin-top: 15px; }
        .store-order-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); gap: 0 18px; }
        .store-order-section { margin-top: 26px; }
        .store-action-overlay {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(38, 0, 26, .58);
        }

        .store-action-overlay.open {
            display: flex;
        }

        .store-action-dialog {
            width: min(100%, 480px);
            max-height: min(90vh, 680px);
            overflow-y: auto;
            padding: 24px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 20px 60px rgba(35, 0, 24, .3);
        }

        .store-action-dialog h2 {
            margin: 0 0 10px;
            color: var(--lv-plum);
        }

        .store-action-dialog p {
            margin: 0;
            color: var(--lv-ink);
        }

        .store-action-dialog textarea {
            width: 100%;
            min-height: 100px;
            margin-top: 16px;
            padding: 12px;
            border: 1px solid var(--lv-line);
            border-radius: 10px;
            font: inherit;
            resize: vertical;
        }

        .store-action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 22px;
        }

        @media (max-width: 480px) {
            .store-action-buttons {
                flex-direction: column-reverse;
            }

            .store-action-buttons .lv-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div id="lv-nav"></div>
    <main class="lv-container">
        <section class="lv-page-head">
            <div class="lv-eyebrow">Partner portal</div>
            <h1 class="lv-title">Store Orders</h1>
            <p class="lv-sub">Confirm incoming orders, receive laundry from riders, and send finished orders back to customers.</p>
        </section>
<?php if ($blocked): ?>
        <section class="lv-card lv-card-pad">
            <h2 class="lv-section-title">Store orders are unavailable</h2>
            <p class="lv-sub"><?= h($blocked) ?></p>
            <a class="lv-btn lv-btn-outline lv-mt" href="Store_Dashboard_Page.php">Back to store dashboard</a>
        </section>
<?php else: ?>
        <p id="ordersError" class="lv-error" role="alert"></p>
        <div id="orderPortal">
            <section class="store-order-section">
                <h2 class="lv-section-title">Incoming orders</h2>
                <p class="lv-sub" style="margin:4px 0 14px;">Review each request. Confirming sends the pickup job to the rider available orders.</p>
                <div class="store-order-list" id="incomingList"></div>
            </section>
            <section class="store-order-section">
                <h2 class="lv-section-title">Rider pickup and processing</h2>
                <p class="lv-sub" style="margin:4px 0 14px;">When the rider arrives with the laundry and cash payment (if selected), confirm receipt to begin processing.</p>
                <div class="store-order-list" id="processingList"></div>
            </section>
            <section class="store-order-section">
                <h2 class="lv-section-title">Processing and return delivery</h2>
                <p class="lv-sub" style="margin:4px 0 14px;">When laundry is finished, mark it for return. Track it while a rider is assigned and on the way to the customer.</p>
                <div class="store-order-list" id="returnList"></div>
            </section>
            <section class="store-order-section">
                <h2 class="lv-section-title">Order history</h2>
                <div class="store-order-list" id="historyList"></div>
            </section>
        </div>
<?php endif; ?>
    </main>
<?php if (!$blocked): ?>
    <div class="store-action-overlay" id="storeActionModal" aria-hidden="true">
        <section class="store-action-dialog" role="dialog" aria-modal="true" aria-labelledby="storeActionTitle"
            aria-describedby="storeActionMessage" tabindex="-1">
            <h2 id="storeActionTitle">Confirm order action</h2>
            <p id="storeActionMessage"></p>
            <label id="rejectReasonLabel" class="lv-label" for="rejectReason" hidden>Reason (optional)</label>
            <textarea id="rejectReason" maxlength="255" placeholder="Add a reason for rejecting this order"
                hidden></textarea>
            <div class="store-action-buttons">
                <button class="lv-btn lv-btn-outline" type="button" id="cancelStoreAction">Cancel</button>
                <button class="lv-btn lv-btn-primary" type="button" id="confirmStoreAction">Confirm</button>
            </div>
        </section>
    </div>
<?php endif; ?>
    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
<?php if (!$blocked): ?>
    <script>
        let orders = [];
        try { LV.renderNav('store', 'orders'); LVApi.fixNavLinks(); } catch (navError) { console.warn('Navbar failed to render', navError); }

        function esc(value) { return LV.esc(value == null ? '' : value); }

        function orderCard(order, actionHtml) {
            const payment = (order.payment || 'cash').toUpperCase() + ' (' + (order.paymentStatus || 'unpaid') + ')';
            return '<article class="lv-card lv-card-pad">' +
                '<div class="store-order-head"><div><div class="lv-eyebrow">Order #' + esc(order.id) + '</div>' +
                '<h3 class="lv-section-title">' + esc(order.customerName || 'Customer') + '</h3></div>' +
                LV.statusBadge(order.status) + '</div>' +
                '<div class="store-order-grid">' +
                '<p class="lv-kv"><span>Bag</span><strong>' + esc(order.bagLabel || 'Laundry') + '</strong></p>' +
                '<p class="lv-kv"><span>Store fee</span><strong>' + LV.peso(order.storeFee) + '</strong></p>' +
                '<p class="lv-kv"><span>Payment</span><strong>' + esc(payment) + '</strong></p>' +
                '<p class="lv-kv"><span>Customer contact</span><strong>' + esc(order.customerContact || '-') + '</strong></p>' +
                '<p class="lv-kv"><span>Customer address</span><strong>' + esc(order.customerAddress || '-') + '</strong></p>' +
                '<p class="lv-kv"><span>Placed</span><strong>' + esc(LV.when(order.createdAt)) + '</strong></p>' +
                '</div>' +
                (order.notes ? '<p class="lv-sub" style="max-width:none;"><strong>Customer note:</strong> ' + esc(order.notes) + '</p>' : '') +
                (order.status === 'rejected' && order.rejectionReason ? '<p class="lv-sub" style="max-width:none;"><strong>Rejection reason:</strong> ' + esc(order.rejectionReason) + '</p>' : '') +
                actionHtml +
                '</article>';
        }

        function emptyCard(message) { return '<div class="lv-card lv-card-pad"><p class="lv-muted">' + message + '</p></div>'; }

        function renderOrders() {
            const pending = orders.filter(function (o) { return o.status === 'pending_store'; });
            const pickup = orders.filter(function (o) { return ['waiting_for_driver', 'enroute_to_store'].indexOf(o.status) > -1; });
            const laundromat = orders.filter(function (o) { return ['at_laundromat', 'waiting_for_return_driver', 'enroute_to_customer'].indexOf(o.status) > -1; });
            const history = orders.filter(function (o) { return ['completed', 'cancelled', 'rejected'].indexOf(o.status) > -1; });

            document.getElementById('incomingList').innerHTML = pending.length ? pending.map(function (order) {
                return orderCard(order, '<div class="store-order-actions">' +
                    '<button class="lv-btn lv-btn-primary lv-btn-sm" onclick="confirmOrder(' + order.id + ')"><i class="fa-solid fa-check"></i> Confirm and send to riders</button>' +
                    '<button class="lv-btn lv-btn-danger lv-btn-sm" onclick="rejectOrder(' + order.id + ')"><i class="fa-solid fa-xmark"></i> Reject</button></div>');
            }).join('') : emptyCard('No new orders are waiting for confirmation.');

            document.getElementById('processingList').innerHTML = pickup.length ? pickup.map(function (order) {
                const action = order.status === 'enroute_to_store'
                    ? '<div class="store-order-actions"><button class="lv-btn lv-btn-primary lv-btn-sm" onclick="confirmDelivery(' + order.id + ')"><i class="fa-solid fa-box"></i> Confirm delivery' +
                        (order.payment === 'cash' && order.paymentStatus !== 'paid' ? ' and cash received' : '') + '</button></div>'
                    : '<p class="lv-muted">Waiting for a rider to accept the pickup job.</p>';
                return orderCard(order, action);
            }).join('') : emptyCard('No orders are currently on the way to your store.');

            document.getElementById('returnList').innerHTML = laundromat.length ? laundromat.map(function (order) {
                const action = order.status === 'at_laundromat'
                    ? '<div class="store-order-actions"><button class="lv-btn lv-btn-pink lv-btn-sm" onclick="markReady(' + order.id + ')"><i class="fa-solid fa-truck-fast"></i> Return to customer</button></div>'
                    : '';
                return orderCard(order, action);
            }).join('') : emptyCard('No orders are being processed or returned.');

            document.getElementById('historyList').innerHTML = history.length ? history.map(function (order) {
                return orderCard(order, '');
            }).join('') : emptyCard('Completed and rejected orders will appear here.');
        }

        async function loadOrders() {
            try {
                orders = (await LVApi.get('../api/store/orders.php')).orders;
                document.getElementById('ordersError').textContent = '';
                renderOrders();
            } catch (error) {
                document.getElementById('ordersError').textContent = error.message;
            }
        }

        const storeActionModal = document.getElementById('storeActionModal');
        let pendingStoreAction = null;
        let lastStoreActionTrigger = null;

        function closeStoreActionModal(restoreFocus) {
            storeActionModal.classList.remove('open');
            storeActionModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            pendingStoreAction = null;
            if (!restoreFocus) return;
            if (lastStoreActionTrigger && lastStoreActionTrigger.isConnected) {
                lastStoreActionTrigger.focus();
            } else {
                const nextAction = document.querySelector('#orderPortal button');
                if (nextAction) nextAction.focus();
            }
        }

        function openStoreActionModal(action, id, trigger) {
            const settings = {
                confirm: ['Confirm order?', 'Confirm this request and send the pickup job to available riders.'],
                reject: ['Reject order?', 'Reject this customer request. You may include an optional reason.'],
                receive: ['Confirm laundry received?', 'Confirm that the rider has delivered the laundry and, if applicable, cash payment.'],
                ready: ['Return order to customer?', 'Mark this finished order as ready and send a return job to available riders.']
            }[action];
            if (!settings) return;
            pendingStoreAction = { action: action, id: id };
            lastStoreActionTrigger = trigger || document.activeElement;
            document.getElementById('storeActionTitle').textContent = settings[0];
            document.getElementById('storeActionMessage').textContent = settings[1];
            const reason = document.getElementById('rejectReason');
            const reasonLabel = document.getElementById('rejectReasonLabel');
            const isReject = action === 'reject';
            reason.hidden = !isReject;
            reasonLabel.hidden = !isReject;
            reason.value = '';
            const confirmButton = document.getElementById('confirmStoreAction');
            confirmButton.textContent = action === 'reject' ? 'Reject order' : 'Confirm';
            confirmButton.className = 'lv-btn ' + (action === 'reject' ? 'lv-btn-danger' : 'lv-btn-primary');
            storeActionModal.classList.add('open');
            storeActionModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            (isReject ? reason : confirmButton).focus();
        }

        function showStoreActionResult(title, message, isError) {
            pendingStoreAction = null;
            document.getElementById('storeActionTitle').textContent = title;
            document.getElementById('storeActionMessage').textContent = message;
            document.getElementById('rejectReason').hidden = true;
            document.getElementById('rejectReasonLabel').hidden = true;
            document.getElementById('cancelStoreAction').hidden = true;
            const confirmButton = document.getElementById('confirmStoreAction');
            confirmButton.textContent = 'Close';
            confirmButton.className = 'lv-btn ' + (isError ? 'lv-btn-danger' : 'lv-btn-primary');
            storeActionModal.classList.add('open');
            storeActionModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            confirmButton.focus();
        }

        function changeOrderStatus(id, status, note) {
            const updated = LV.setOrderStatus(id, status, note, store.id);
            if (!updated) {
                return false;
            }
            renderOrders();
            return true;
        }

        window.confirmOrder = function (id) { openStoreActionModal('confirm', id); };
        window.rejectOrder = function (id) { openStoreActionModal('reject', id); };
        window.confirmDelivery = function (id) { openStoreActionModal('receive', id); };
        window.markReady = function (id) { openStoreActionModal('ready', id); };

        // changeOrderStatus: one call to the PHP API (the server re-checks the order's current state)
        async function changeOrderStatus(id, action, reason) {
            const result = await LVApi.postJson('../api/store/orders.php', { action: action, order_id: id, reason: reason || '' });
            orders = result.orders;
            renderOrders();
        }

        window.confirmOrder = function (id) { openStoreActionModal('confirm', id); };
        window.rejectOrder = function (id) { openStoreActionModal('reject', id); };
        window.confirmDelivery = function (id) { openStoreActionModal('receive', id); };
        window.markReady = function (id) { openStoreActionModal('ready', id); };

        document.getElementById('cancelStoreAction').addEventListener('click', function () {
            closeStoreActionModal(true);
        });
        document.getElementById('confirmStoreAction').addEventListener('click', async function () {
            const cancelButton = document.getElementById('cancelStoreAction');
            if (!pendingStoreAction) {                 // showing a result: this button just closes it
                cancelButton.hidden = false;
                closeStoreActionModal(true);
                return;
            }
            const action = pendingStoreAction.action;
            const id = pendingStoreAction.id;
            const reason = document.getElementById('rejectReason').value.trim();
            const transitions = {
                confirm: ['confirm', 'Order confirmed and sent to available riders.'],
                reject: ['reject', 'Order rejected.'],
                receive: ['confirm_delivery', 'Laundry received. The order is now at the laundromat.'],
                ready: ['ready_for_return', 'Order marked ready and sent to available riders.']
            };
            const transition = transitions[action];
            if (!transition) return;
            this.disabled = true;
            try {
                await changeOrderStatus(id, transition[0], action === 'reject' ? reason : '');
                cancelButton.hidden = true;
                showStoreActionResult('Order updated', transition[1], false);
            } catch (error) {
                cancelButton.hidden = true;
                showStoreActionResult('Unable to update order', error.message || 'This order could not be updated. Refresh the page and try again.', true);
                loadOrders();                          // re-sync with the server
            }
            this.disabled = false;
        });
        storeActionModal.addEventListener('click', function (event) {
            if (event.target === storeActionModal) {
                document.getElementById('cancelStoreAction').hidden = false;
                closeStoreActionModal(true);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (!storeActionModal.classList.contains('open')) return;
            if (event.key === 'Escape') {
                document.getElementById('cancelStoreAction').hidden = false;
                closeStoreActionModal(true);
            } else if (event.key === 'Tab') {
                const focusable = Array.from(storeActionModal.querySelectorAll('button:not([hidden]), textarea:not([hidden])'));
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        loadOrders();
        // pick up new customer orders and rider progress without a manual reload
        setInterval(function () { if (!document.hidden) loadOrders(); }, 30000);
    </script>
<?php endif; ?>
</body>
</html>
