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
    <title>Admin Dashboard - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">



        <!-- PAGE HEAD -->
        <section class="lv-page-head">
            <div class="lv-eyebrow">Platform overview</div>
            <h1 class="lv-title">Admin Dashboard</h1>
            <p class="lv-sub">Orders, stores and riders across LaVanDera at a glance.</p>
        </section>

        <!-- GREETING BANNER -->
        <section class="lv-hero" style="margin-top:4px;">
            <div style="position:relative; z-index:1;">
                <h1><span id="heroGreet">Welcome, Admin!</span>
                    <i class="fa-solid fa-hand-sparkles" style="font-size:0.7em;"></i>
                </h1>
                <p id="heroLine">Loading platform activity&hellip;</p>
                <div class="lv-hero-cta lv-wrap">
                    <a href="Admin_Riders_Page.php" class="lv-btn lv-btn-ghost lv-btn-sm">
                        <i class="fa-solid fa-motorcycle"></i> Review pending riders
                        <span id="pendingRidersCount">(0)</span>
                    </a>
                    <a href="Admin_Stores_Page.php" class="lv-btn lv-btn-ghost lv-btn-sm">
                        <i class="fa-solid fa-store"></i> Review pending stores
                        <span id="pendingStoresCount">(0)</span>
                    </a>
                </div>
            </div>
        </section>

        <!-- STAT GRID -->
        <section class="lv-grid lv-grid-4" style="margin-top:22px;" id="statGrid"></section>

        <!-- BREAKDOWN + APPROVALS -->
        <section class="lv-grid lv-grid-2 lv-mt">
            <div class="lv-card lv-card-pad">
                <div class="lv-row-between" style="margin-bottom:18px;">
                    <div>
                        <div class="lv-eyebrow">Fulfilment flow</div>
                        <h2 class="lv-section-title">Order status breakdown</h2>
                    </div>
                    <span class="lv-badge lv-tone-pink" id="breakdownTotal">0 orders</span>
                </div>
                <div id="breakdownBody"></div>
            </div>

            <div class="lv-card lv-card-pad">
                <div class="lv-row-between" style="margin-bottom:18px;">
                    <div>
                        <div class="lv-eyebrow">Needs your attention</div>
                        <h2 class="lv-section-title">Pending approvals</h2>
                    </div>
                    <span class="lv-badge lv-tone-amber" id="approvalCount">0 waiting</span>
                </div>
                <div id="approvalsBody"></div>
            </div>
        </section>

        <!-- RECENT ORDERS -->
        <section class="lv-mt">
            <div class="lv-row-between" style="margin-bottom:16px;">
                <div>
                    <div class="lv-eyebrow">Latest activity</div>
                    <h2 class="lv-section-title">Recent orders</h2>
                </div>
                <span class="lv-muted" style="font-size:13.5px;">Newest first</span>
            </div>
            <div class="lv-card" id="recentCard"></div>
        </section>

    </main>

    <script>
        window.LV_CONFIG = <?= js_json($lvConfig) ?>;
        window.LV_SESSION = <?= js_json($lvSession) ?>;
    </script>
    <script src="../js/lavandera_demo.js"></script>
    <script src="../js/lavandera_api.js"></script>
    <script>
        let data = null;

        function showLoadError(message) {
            document.querySelector('main').insertAdjacentHTML('beforeend',
                '<section class="lv-card lv-card-pad lv-mt"><h2 class="lv-section-title">Could not load the dashboard</h2>' +
                '<p class="lv-sub">' + LV.esc(message) + '</p>' +
                '<button class="lv-btn lv-btn-primary lv-mt" onclick="location.reload()">Try again</button></section>');
        }

        init();

        async function init() {
            try { LV.renderNav('admin', 'dashboard'); LVApi.fixNavLinks(); } catch (navError) { console.warn('Navbar failed to render', navError); }
            try {
                data = await LVApi.get('../api/admin/dashboard.php');
                render();
            } catch (error) {
                document.getElementById('heroLine').textContent = 'Platform activity could not be loaded.';
                showLoadError(error.message);
            }
        }

        function render() {
            const s = data.stats;
            const pending = s.pendingRiders + s.pendingStores;

            /* ---- hero ---- */
            const hour = new Date().getHours();
            document.getElementById('heroGreet').textContent =
                (hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening') + ', Admin!';
            document.getElementById('heroLine').textContent =
                s.inProgress + ' order' + (s.inProgress === 1 ? '' : 's') + ' in progress across ' +
                s.activeStores + ' partner store' + (s.activeStores === 1 ? '' : 's') + ', with ' +
                s.totalOrders + ' order' + (s.totalOrders === 1 ? '' : 's') + ' placed in total.';
            document.getElementById('pendingRidersCount').textContent = '(' + s.pendingRiders + ')';
            document.getElementById('pendingStoresCount').textContent = '(' + s.pendingStores + ')';

            /* ---- stat tiles ---- */
            const stats = [
                { icon: 'fa-users', value: s.totalCustomers, label: 'Total customers' },
                { icon: 'fa-store', value: s.activeStores, label: 'Active stores' },
                { icon: 'fa-motorcycle', value: s.activeRiders, label: 'Active riders' },
                { icon: 'fa-basket-shopping', value: s.totalOrders, label: 'Total orders' },
                { icon: 'fa-arrows-rotate', value: s.inProgress, label: 'In-progress orders' },
                { icon: 'fa-circle-check', value: s.completedOrders, label: 'Completed orders' },
                { icon: 'fa-user-clock', value: pending, label: 'Pending approvals', amber: pending > 0 },
                { icon: 'fa-sack-dollar', value: LV.peso(s.revenue), label: 'Completed order value' }
            ];
            document.getElementById('statGrid').innerHTML = stats.map(function (t) {
                return '<div class="lv-card lv-stat">' +
                    '<div class="lv-stat-icon"' + (t.amber ? ' style="background:var(--lv-amber-bg); color:var(--lv-amber);"' : '') + '>' +
                    '<i class="fa-solid ' + t.icon + '"></i></div>' +
                    '<div class="lv-stat-value">' + t.value + '</div>' +
                    '<div class="lv-stat-label">' + t.label + '</div></div>';
            }).join('');

            renderBreakdown(s);
            renderApprovals();
            renderRecent();
        }

        /* ---------------------------------------------- breakdown ---- */
        function renderBreakdown(s) {
            const rows = LV.FLOW.concat(['rejected', 'cancelled']).map(function (k) {
                return { status: k, label: (LV.STATUS[k] || {}).label || k, count: data.statusCounts[k] || 0 };
            });
            const nonzero = rows.filter(function (r) { return r.count > 0; });
            const total = s.totalOrders || rows.reduce(function (t, r) { return t + r.count; }, 0);
            const host = document.getElementById('breakdownBody');
            document.getElementById('breakdownTotal').textContent = total + ' order' + (total === 1 ? '' : 's');

            if (!nonzero.length) {
                host.innerHTML = '<div class="lv-empty" style="padding:34px 10px;">' +
                    '<i class="fa-solid fa-basket-shopping"></i>' +
                    '<h3>No orders yet</h3><p>Status bars will appear as soon as orders come in.</p></div>';
                return;
            }

            host.innerHTML = nonzero.map(function (r) {
                const pct = total ? Math.round((r.count / total) * 1000) / 10 : 0;
                return '<div style="margin-bottom:16px;">' +
                    '<div class="lv-row-between" style="margin-bottom:7px; gap:10px;">' +
                    '<span style="font-size:14px; font-weight:600;">' + LV.esc(r.label) + '</span>' +
                    '<span style="font-size:14px; font-weight:800; color:var(--lv-plum);">' + r.count + '</span>' +
                    '</div>' +
                    '<div style="height:8px; border-radius:999px; background:var(--lv-pink-tint); overflow:hidden;">' +
                    '<div style="height:100%; width:' + pct + '%; border-radius:999px; background:var(--lv-pink);"></div>' +
                    '</div></div>';
            }).join('');
        }

        /* --------------------------------------------- approvals ----- */
        function renderApprovals() {
            const items = data.approvals.map(function (a) {
                return { name: a.name, joined: a.joined, type: a.type, href: a.type === 'Store' ? 'Admin_Stores_Page.php' : 'Admin_Riders_Page.php' };
            });

            document.getElementById('approvalCount').textContent = items.length + ' waiting';
            const host = document.getElementById('approvalsBody');

            if (!items.length) {
                host.innerHTML = '<div class="lv-tone-green" style="border-radius:16px; padding:16px; text-align:center; ' +
                    'font-size:14px; font-weight:700;">' +
                    '<i class="fa-solid fa-circle-check"></i> Nothing waiting for approval. All caught up!</div>';
                return;
            }

            host.innerHTML = items.map(function (i, idx) {
                return '<div class="lv-row-between" style="padding:12px 0;' +
                    (idx < items.length - 1 ? ' border-bottom:1px dashed var(--lv-line);' : '') + '">' +
                    '<div class="lv-row" style="gap:12px; min-width:0;">' +
                    '<div class="lv-avatar lv-avatar-sm">' + LV.esc(LV.initials(i.name)) + '</div>' +
                    '<div style="min-width:0;">' +
                    '<div class="lv-row" style="gap:8px; flex-wrap:wrap;">' +
                    '<span style="font-weight:800; color:var(--lv-plum); font-size:14.5px;">' + LV.esc(i.name) + '</span>' +
                    '<span class="lv-badge lv-tone-pink" style="font-size:10.5px; padding:3px 9px;">' + i.type + '</span>' +
                    '</div>' +
                    '<div class="lv-muted" style="font-size:12.5px;">joined ' + LV.esc(LV.when(i.joined)) + '</div>' +
                    '</div>' +
                    '</div>' +
                    '<a class="lv-btn lv-btn-outline lv-btn-sm" href="' + i.href + '">Review</a>' +
                    '</div>';
            }).join('');
        }

        /* ----------------------------------------- recent orders ----- */
        function renderRecent() {
            const recent = data.recent;
            const host = document.getElementById('recentCard');

            if (!recent.length) {
                host.innerHTML = '<div class="lv-empty"><i class="fa-solid fa-basket-shopping"></i>' +
                    '<h3>No orders yet</h3><p>New orders will show up here the moment customers place them.</p></div>';
                return;
            }

            const head = '<thead><tr>' +
                '<th>Order</th><th>Customer</th><th>Store</th><th>Bag</th>' +
                '<th>Total</th><th>Status</th><th>Placed</th></tr></thead>';

            const body = recent.map(function (o) {
                return '<tr>' +
                    '<td style="font-weight:800; color:var(--lv-plum);">#' + o.id + '</td>' +
                    '<td>' + LV.esc(o.customerName || '-') + '</td>' +
                    '<td>' + LV.esc(o.storeName || '-') + '</td>' +
                    '<td>' + LV.esc(o.bag) + '</td>' +
                    '<td style="font-weight:800;">' + LV.peso(o.total) + '</td>' +
                    '<td>' + LV.statusBadge(o.status) + '</td>' +
                    '<td class="lv-muted" style="font-size:13px; white-space:nowrap;">' + LV.when(o.createdAt) + '</td>' +
                    '</tr>';
            }).join('');

            host.innerHTML = '<div class="lv-table-wrap"><table class="lv-table">' + head +
                '<tbody>' + body + '</tbody></table></div>';
        }
    </script>
</body>

</html>
