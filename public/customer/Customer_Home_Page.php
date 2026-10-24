<?php
/**
 * Customer Home page (converted to PHP).
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
    <title>Home - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">
        <p class="lv-error" id="homeError" role="alert"></p>

        <!-- WELCOME BANNER -->
        <section class="lv-hero" style="margin-top:28px;">
            <div class="lv-row" style="gap:22px; align-items:center; position:relative; z-index:1;">
                <div class="lv-avatar lv-avatar-lg" id="heroAvatar" style="display:none;"></div>
                <div>
                    <h1>Welcome back, <span id="heroName">friend</span>! <i class="fa-solid fa-hand-sparkles"
                            style="font-size:0.7em;"></i></h1>
                    <p id="heroLine">Your laundry is one click away - pick a shop and we handle the rest.</p>
                    <div class="lv-hero-cta lv-wrap">
                        <a href="Customer_Place_Order_Page.php" class="lv-btn lv-btn-ghost lv-btn-sm">
                            <i class="fa-solid fa-plus"></i> Place an Order
                        </a>
                        <a href="Customer_Stores_Page.php" class="lv-btn lv-btn-ghost lv-btn-sm">
                            <i class="fa-solid fa-store"></i> Browse Stores
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- STATS -->
        <section class="lv-grid lv-grid-4" style="margin-top:22px;" id="statGrid"></section>

        <!-- FEATURED STORES -->
        <section class="lv-mt">
            <div class="lv-row-between" style="margin-bottom:16px;">
                <div>
                    <div class="lv-eyebrow">Hand-picked for you</div>
                    <h2 class="lv-section-title">Featured Stores</h2>
                </div>
                <a href="Customer_Stores_Page.php" class="lv-link" style="font-size:14px;">View all stores
                    <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i></a>
            </div>
            <div class="lv-grid lv-grid-3" id="featuredGrid"></div>
        </section>

        <!-- RECENT ORDERS -->
        <section class="lv-mt">
            <div class="lv-row-between" style="margin-bottom:16px;">
                <div>
                    <div class="lv-eyebrow">Live tracking</div>
                    <h2 class="lv-section-title">Your Recent Orders</h2>
                </div>
                <a href="Customer_My_Orders_Page.php" class="lv-link" style="font-size:14px;">All orders
                    <i class="fa-solid fa-arrow-right" style="font-size:12px;"></i></a>
            </div>
            <div class="lv-grid" id="recentOrders"></div>
        </section>

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
        try { LV.renderNav('customer', 'home'); LVApi.fixNavLinks(); LVLinks.fix(); } catch (navError) { console.warn('Navbar failed to render', navError); }

        // Images are saved in the database as paths relative to public/ (e.g. uploads/stores/x.jpg); pages live one folder deeper.
        const imgUrl = function (path) { return path ? '../' + path : ''; };
        const IMG_FALLBACK = "this.onerror=null; this.className='lv-media lv-banner lv-fallback'; this.removeAttribute('src');";

        let orders = [];
        let stores = [];

        // Fetch BOTH lists at once (Promise.all waits for both), then draw the page.
        async function load() {
            try {
                const replies = await Promise.all([
                    LVApi.get('../api/customer/orders.php'),
                    LVApi.get('../api/customer/stores.php')
                ]);
                orders = replies[0].orders;
                stores = replies[1].stores;
                render();
            } catch (error) {
                document.getElementById('homeError').textContent = error.message;
            }
        }
        load();

        function render() {
            /* ---- hero ---- */
            const first = String(me.name).split(' ')[0];
            document.getElementById('heroName').textContent = first;
            const av = document.getElementById('heroAvatar');
            av.style.display = 'grid';
            av.textContent = LV.initials(me.name);
            const hour = new Date().getHours();
            document.getElementById('heroLine').textContent =
                (hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening') +
                ', ' + first + '! Your laundry is one click away - pick a shop and we handle the rest.';

            /* ---- stats (computed from the orders the server sent) ---- */
            const active = orders.filter(function (o) {
                return ['pending_store', 'waiting_for_driver', 'enroute_to_store', 'at_laundromat',
                        'waiting_for_return_driver', 'enroute_to_customer'].indexOf(o.status) > -1;
            });
            const done = orders.filter(function (o) { return o.status === 'completed'; });
            const spent = done.reduce(function (t, o) { return t + Number(o.total); }, 0);
            const tiles = [
                { icon: 'fa-basket-shopping', value: active.length, label: 'Active Orders' },
                { icon: 'fa-check-double',    value: done.length,   label: 'Completed Orders' },
                { icon: 'fa-store',           value: stores.length, label: 'Partner Stores' },
                { icon: 'fa-wallet',          value: LV.peso(spent), label: 'Total Spent' }
            ];
            document.getElementById('statGrid').innerHTML = tiles.map(function (s) {
                return '<div class="lv-card lv-stat">' +
                    '<div class="lv-stat-icon"><i class="fa-solid ' + s.icon + '"></i></div>' +
                    '<div class="lv-stat-value">' + s.value + '</div>' +
                    '<div class="lv-stat-label">' + s.label + '</div></div>';
            }).join('');

            /* ---- featured stores ---- */
            const featured = stores.filter(function (s) { return s.featured; });
            document.getElementById('featuredGrid').innerHTML = featured.map(storeCard).join('') ||
                '<div class="lv-card lv-empty"><i class="fa-solid fa-store"></i><h3>No featured stores yet</h3>' +
                '<p>Browse all partner shops instead.</p></div>';

            /* ---- recent orders ---- */
            const recent = orders.slice(0, 3);
            document.getElementById('recentOrders').innerHTML = recent.length
                ? recent.map(orderRow).join('')
                : '<div class="lv-card lv-empty"><i class="fa-solid fa-basket-shopping"></i>' +
                  '<h3>No orders yet</h3><p>Place your first laundry order to see it tracked here.</p>' +
                  '<a href="Customer_Place_Order_Page.php" class="lv-btn lv-btn-primary lv-btn-sm" style="margin-top:14px;">Place an Order</a></div>';
        }

        function storeCard(s) {
            // LV.esc() turns < > & " into harmless text: names and addresses are typed by users.
            const banner = s.banner
                ? '<img class="lv-media lv-banner" src="' + LV.esc(imgUrl(s.banner)) + '" alt="' + LV.esc(s.name) + '" onerror="' + IMG_FALLBACK + '">'
                : '<div class="lv-media lv-banner lv-fallback"></div>';
            const distance = s.distanceKm != null ? s.distanceKm.toFixed(1) + ' km away' : 'Pin your address to see distance';
            return '<a class="lv-card lv-card-click" href="Customer_View_Store_Page.php?id=' + s.id + '" style="overflow:hidden; display:block;">' +
                banner +
                '<div class="lv-card-pad">' +
                    '<div class="lv-row-between" style="margin-bottom:8px;">' +
                        (s.featured ? '<span class="lv-badge lv-tone-pink"><i class="fa-solid fa-star"></i>Featured</span>' : '<span></span>') +
                        (s.freeDelivery ? '<span class="lv-badge lv-tone-green"><i class="fa-solid fa-truck-fast"></i>Free delivery</span>' : '') +
                    '</div>' +
                    '<h3 style="font-size:17px; color:var(--lv-plum); margin-bottom:4px;">' + LV.esc(s.name) + '</h3>' +
                    '<p class="lv-muted" style="font-size:13px; margin-bottom:12px;">' + LV.esc(s.address || 'Address to be confirmed') + '</p>' +
                    '<div class="lv-row-between" style="border-top:1px solid var(--lv-line); padding-top:12px;">' +
                        '<span style="font-weight:800; color:var(--lv-pink);">from ' + LV.peso(s.prices.small) + '</span>' +
                        '<span class="lv-muted" style="font-size:12.5px;"><i class="fa-solid fa-location-dot"></i> ' + distance + '</span>' +
                    '</div>' +
                '</div></a>';
        }

        function orderRow(o) {
            const bag = LV.bagOf(o.bag);
            return '<a class="lv-card lv-card-pad lv-row-between lv-card-click" href="Customer_My_Orders_Page.php?order=' + o.id + '" style="display:flex;">' +
                '<div class="lv-row">' +
                    '<div class="lv-avatar"><i class="fa-solid ' + bag.icon + '" style="font-size:18px;"></i></div>' +
                    '<div>' +
                        '<div style="font-weight:800; color:var(--lv-plum);">' + LV.esc(o.storeName || 'Store') + '</div>' +
                        '<div class="lv-muted" style="font-size:13px;">#' + o.id + ' &middot; ' + bag.name + ' (' + bag.kg + 'kg) &middot; ' + LV.when(o.createdAt) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="lv-row" style="gap:16px;">' +
                    '<span style="font-weight:800;">' + LV.peso(o.total) + '</span>' +
                    LV.statusBadge(o.status) +
                '</div></a>';
        }
    </script>
</body>

</html>
