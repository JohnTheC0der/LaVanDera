<?php
require __DIR__ . '/../../src/bootstrap.php';
$user = guard_admin_page();
$lvConfig  = ['loginUrl' => config('login_url'), 'logoutUrl' => '../auth/logout.php', 'csrf' => csrf_token()];
$lvSession = ['id' => $user['id'], 'role' => 'admin', 'name' => $user['name'], 'email' => $user['email']];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <style>
        /* Page-level addition: lightweight customer detail modal
           (the shared theme has no modal component). */
        .lv-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1100;
            background: rgba(43, 13, 30, 0.45);
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px);
            display: grid;
            place-items: center;
            padding: 20px;
            overflow-y: auto;
            overscroll-behavior: contain;
            visibility: hidden;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease, visibility 0s linear 0.18s;
        }

        .lv-modal-overlay.open {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
            transition-delay: 0s;
        }

        body.lv-modal-open {
            overflow: hidden;
        }

        .lv-modal {
            background: #fff;
            border: 1px solid var(--lv-line);
            border-radius: 24px;
            box-shadow: var(--lv-shadow-lg);
            width: 100%;
            max-width: 560px;
            max-height: 85vh;
            max-height: calc(100vh - 40px);
            max-height: calc(100dvh - 40px);
            margin-block: auto;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 26px;
        }
    </style>
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">

        <!-- PAGE HEAD -->
        <section class="lv-page-head">
            <div class="lv-eyebrow">User management</div>
            <h1 class="lv-title">Customers</h1>
            <p class="lv-sub">Everyone registered on the platform &mdash; <span id="custCount"
                    style="font-weight:800; color:var(--lv-plum);">0</span> accounts and counting.</p>
        </section>

        <!-- STATS STRIP -->
        <section class="lv-grid lv-grid-3" id="statStrip"></section>

        <!-- SEARCH TOOLBAR -->
        <section class="lv-card lv-card-pad lv-mt">
            <div class="lv-row-between" style="flex-wrap:wrap; gap:14px;">
                <div class="lv-search" style="flex:1; min-width:260px;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="custSearch" class="lv-input" type="text" autocomplete="off"
                        aria-label="Search customers by name, email, or contact number"
                        placeholder="Search by name, email or contact number...">
                </div>
                <span class="lv-muted" id="showingNote" role="status" aria-live="polite"
                    style="font-size:13.5px; white-space:nowrap;"></span>
            </div>
        </section>

        <!-- CUSTOMERS TABLE -->
        <section class="lv-mt" id="tableCard"></section>

    </main>

    <!-- CUSTOMER DETAIL MODAL -->
    <div class="lv-modal-overlay" id="custOverlay" aria-hidden="true">
        <div class="lv-modal" role="dialog" aria-modal="true" aria-label="Customer details" id="custModal"
            tabindex="-1"></div>
    </div>

    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
    <script>
        let customers = [];
        let totals = { totalOrders: 0, lifetime: 0 };
        let lastFocusedElement = null;

        function showLoadError(message) {
            document.getElementById('tableCard').innerHTML =
                '<div class="lv-card lv-card-pad"><h2 class="lv-section-title">Could not load customers</h2>' +
                '<p class="lv-sub">' + LV.esc(message) + '</p>' +
                '<button class="lv-btn lv-btn-primary lv-mt" onclick="location.reload()">Try again</button></div>';
        }

        try { LV.renderNav('admin', 'customers'); LVApi.fixNavLinks(); } catch (navError) { console.warn('Navbar failed to render', navError); }
        document.getElementById('custSearch').addEventListener('input', renderTable);
        document.getElementById('tableCard').addEventListener('click', function (event) {
            const button = event.target.closest('[data-customer-id]');
            if (button) openCustomer(button.dataset.customerId);
        });
        document.getElementById('custOverlay').addEventListener('click', function (event) {
            if (event.target === this) closeModal();
        });
        document.getElementById('custModal').addEventListener('click', function (event) {
            if (event.target.closest('[data-modal-close]')) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            const overlay = document.getElementById('custOverlay');
            if (!overlay.classList.contains('open')) return;
            if (e.key === 'Escape') {
                closeModal();
                return;
            }
            if (e.key !== 'Tab') return;

            const focusable = Array.from(document.getElementById('custModal').querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
                'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            ));
            if (!focusable.length) {
                e.preventDefault();
                document.getElementById('custModal').focus();
                return;
            }

            const first = focusable[0], last = focusable[focusable.length - 1];
            if (e.shiftKey && (document.activeElement === first ||
                !document.getElementById('custModal').contains(document.activeElement))) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && (document.activeElement === last ||
                !document.getElementById('custModal').contains(document.activeElement))) {
                e.preventDefault();
                first.focus();
            }
        });

        load();

        async function load() {
            try {
                const data = await LVApi.get('../api/admin/customers.php');
                customers = data.customers;
                totals = data.stats;
                render();
            } catch (error) {
                showLoadError(error.message);
            }
        }

        function render() {
            document.getElementById('custCount').textContent = customers.length;

            const stats = [
                { icon: 'fa-users', value: customers.length, label: 'Total customers' },
                { icon: 'fa-basket-shopping', value: totals.totalOrders, label: 'Orders placed by customers' },
                { icon: 'fa-sack-dollar', value: LV.peso(totals.lifetime), label: 'Customer lifetime value' }
            ];
            document.getElementById('statStrip').innerHTML = stats.map(function (t) {
                return '<div class="lv-card lv-stat">' +
                    '<div class="lv-stat-icon"><i class="fa-solid ' + t.icon + '"></i></div>' +
                    '<div class="lv-stat-value">' + t.value + '</div>' +
                    '<div class="lv-stat-label">' + t.label + '</div></div>';
            }).join('');

            renderTable();
        }

        /* ------------------------------------------ table render ----- */
        function renderTable() {
            const all = customers;
            const q = (document.getElementById('custSearch').value || '').trim().toLowerCase();
            const list = all.filter(function (c) {
                const haystack = String(c.name || '') + ' ' + String(c.email || '') + ' ' + String(c.contact || '');
                return !q || haystack.toLowerCase().indexOf(q) > -1;
            });

            document.getElementById('showingNote').textContent =
                'Showing ' + list.length + ' of ' + all.length + ' customer' + (all.length === 1 ? '' : 's');

            const host = document.getElementById('tableCard');
            if (!list.length) {
                const emptyTitle = all.length ? 'No customers found' : 'No customers yet';
                const emptyMessage = all.length
                    ? 'Try a different name, email or contact number.'
                    : 'Customer accounts will appear here when they register.';
                host.innerHTML = '<div class="lv-card lv-empty"><i class="fa-solid fa-users-slash"></i>' +
                    '<h3>' + emptyTitle + '</h3>' +
                    '<p>' + emptyMessage + '</p></div>';
                return;
            }

            const head = '<thead><tr>' +
                '<th scope="col">Customer</th><th scope="col">Contact number</th><th scope="col">Address</th>' +
                '<th scope="col">Orders</th><th scope="col">Spent</th><th scope="col">Actions</th></tr></thead>';

            const body = list.map(function (c) {
                const addr = LV.esc(c.address || 'Address to be confirmed');
                return '<tr>' +
                    '<td><div class="lv-row" style="gap:12px;">' +
                    '<div class="lv-avatar lv-avatar-sm">' + LV.esc(LV.initials(c.name)) + '</div>' +
                    '<div style="min-width:0;">' +
                    '<div style="font-weight:800; color:var(--lv-plum);">' + LV.esc(c.name) + '</div>' +
                    '<div class="lv-muted" style="font-size:12.5px;">' + LV.esc(c.email || '-') + '</div>' +
                    '</div></div></td>' +
                    '<td style="white-space:nowrap;">' + LV.esc(c.contact || '-') + '</td>' +
                    '<td><span style="display:inline-block; max-width:220px; white-space:nowrap; ' +
                    'overflow:hidden; text-overflow:ellipsis; vertical-align:middle;" title="' + addr + '">' +
                    addr + '</span></td>' +
                    '<td style="font-weight:800;">' + c.orders + '</td>' +
                    '<td style="font-weight:800;">' + LV.peso(c.spent) + '</td>' +
                    '<td><button class="lv-btn lv-btn-outline lv-btn-sm" data-customer-id="' + LV.esc(c.id) + '">' +
                    '<i class="fa-solid fa-eye" style="font-size:12px;"></i> View details</button></td>' +
                    '</tr>';
            }).join('');

            host.innerHTML = '<div class="lv-card"><div class="lv-table-wrap">' +
                '<table class="lv-table" aria-label="Customer accounts">' + head +
                '<tbody>' + body + '</tbody></table></div></div>';
        }

        /* ------------------------------------------ detail modal ----- */
        async function openCustomer(id) {
            const trigger = document.activeElement;
            let c;
            try {
                c = (await LVApi.get('../api/admin/customers.php?id=' + encodeURIComponent(id))).customer;
            } catch (error) {
                LV.toast(error.message, 'fa-circle-info');
                return;
            }

            const orders = c.orderList;
            const spend = c.spent;

            const orderRows = orders.length ? orders.map(function (o) {
                return '<div style="padding:11px 0; border-bottom:1px dashed var(--lv-line);">' +
                    '<div class="lv-row" style="flex-wrap:wrap; gap:8px;">' +
                    '<span style="font-weight:800; color:var(--lv-plum);">#' + o.id + '</span>' +
                    '<span class="lv-muted">&middot; ' + LV.esc(o.storeName || 'Store') + '</span>' +
                    '<span class="lv-muted">&middot; ' + LV.esc(o.bag) + '</span>' +
                    '<span style="font-weight:800;">' + LV.peso(o.total) + '</span>' +
                    LV.statusBadge(o.status) +
                    '<span class="lv-muted" style="font-size:12.5px;">' + LV.when(o.createdAt) + '</span>' +
                    '</div></div>';
            }).join('') : '<div class="lv-muted" style="font-size:14px; padding:12px 0;">' +
            'No orders placed yet.</div>';

            document.getElementById('custModal').innerHTML =
                '<div class="lv-row-between" style="margin-bottom:18px;">' +
                '<span class="lv-eyebrow" style="margin:0;">Customer details</span>' +
                '<button class="lv-btn lv-btn-outline lv-btn-sm" data-modal-close aria-label="Close">' +
                '<i class="fa-solid fa-xmark"></i> Close</button>' +
                '</div>' +
                '<div class="lv-row" style="gap:16px; margin-bottom:16px;">' +
                '<div class="lv-avatar lv-avatar-lg">' + LV.esc(LV.initials(c.name)) + '</div>' +
                '<div style="min-width:0;">' +
                '<div style="font-weight:800; font-size:20px; color:var(--lv-plum);">' + LV.esc(c.name) + '</div>' +
                '<div class="lv-muted" style="font-size:13.5px; word-break:break-all;">' + LV.esc(c.email || '-') + '</div>' +
                '</div>' +
                '</div>' +
                '<div>' +
                '<div class="lv-kv"><span>Contact number</span><span>' + LV.esc(c.contact || '-') + '</span></div>' +
                '<div class="lv-kv"><span>Address</span><span>' + LV.esc(c.address || '-') + '</span></div>' +
                '<div class="lv-kv"><span>First order</span><span>' +
                (c.firstOrder ? LV.when(c.firstOrder) : 'No orders yet') + '</span></div>' +
                '</div>' +
                '<hr class="lv-divider">' +
                '<div class="lv-eyebrow" style="margin-bottom:8px;">Orders (' + orders.length + ')</div>' +
                orderRows +
                '<div style="margin-top:16px; padding:14px 16px; background:var(--lv-pink-tint); border-radius:16px; ' +
                'font-size:14px; font-weight:700; color:var(--lv-plum);">' +
                'Lifetime spend: ' + LV.peso(spend) + ' across ' + c.completedCount +
                ' completed order' + (c.completedCount === 1 ? '' : 's') + '</div>';

            lastFocusedElement = trigger;
            const overlay = document.getElementById('custOverlay');
            overlay.setAttribute('aria-hidden', 'false');
            overlay.classList.add('open');
            document.body.classList.add('lv-modal-open');
            document.querySelector('[data-modal-close]').focus();
        }

        function closeModal() {
            const overlay = document.getElementById('custOverlay');
            if (!overlay.classList.contains('open')) return;
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('lv-modal-open');
            if (lastFocusedElement && lastFocusedElement.isConnected) lastFocusedElement.focus();
            lastFocusedElement = null;
        }
    </script>
</body>

</html>
