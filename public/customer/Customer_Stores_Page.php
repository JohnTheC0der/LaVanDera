<?php
/**
 * Customer Stores page (converted to PHP).
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
    <title>Stores - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">

        <!-- PAGE HEAD -->
        <section class="lv-page-head">
            <div class="lv-eyebrow">Partner laundromats</div>
            <h1 class="lv-title">All Stores</h1>
            <p class="lv-sub">Every partner shop on LaVanDera - with pickup + delivery anywhere in Pasig City,
                your laundry never has to leave your neighborhood.</p>
        </section>

        <!-- TOOLBAR: search + filters -->
        <section class="lv-card lv-card-pad" style="display:flex; flex-direction:column; gap:14px;">
            <div class="lv-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="storeSearch" class="lv-input" type="text" placeholder="Search store name or address..." autocomplete="off">
            </div>
            <div class="lv-wrap" id="filterChips">
                <button type="button" class="lv-btn lv-btn-primary lv-btn-sm" data-filter="all">
                    <i class="fa-solid fa-layer-group"></i> All</button>
                <button type="button" class="lv-btn lv-btn-outline lv-btn-sm" data-filter="featured">
                    <i class="fa-solid fa-star"></i> Featured</button>
                <button type="button" class="lv-btn lv-btn-outline lv-btn-sm" data-filter="free">
                    <i class="fa-solid fa-truck-fast"></i> Free delivery</button>
                <button type="button" class="lv-btn lv-btn-outline lv-btn-sm" data-filter="cheapest">
                    <i class="fa-solid fa-tag"></i> Cheapest small bag</button>
            </div>
            <div id="resultCount" class="lv-muted" style="font-size:13.5px; font-weight:600;"></div>
        </section>

        <p class="lv-error" id="storesError" role="alert"></p>

        <!-- STORE GRID -->
        <div class="lv-grid lv-grid-3 lv-mt" id="storeGrid"></div>

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
        try { LV.renderNav('customer', 'stores'); LVApi.fixNavLinks(); LVLinks.fix(); } catch (navError) { console.warn('Navbar failed to render', navError); }

        // Images are saved in the database as paths relative to public/ (e.g. uploads/stores/x.jpg); pages live one folder deeper.
        const imgUrl = function (path) { return path ? '../' + path : ''; };
        const IMG_FALLBACK = "this.onerror=null; this.className='lv-media lv-banner lv-fallback'; this.removeAttribute('src');";

        let searchTerm = '';
        let activeFilter = 'all';
        let stores = [];

        async function load() {
            try {
                stores = (await LVApi.get('../api/customer/stores.php')).stores;
                render();
            } catch (error) {
                document.getElementById('storesError').textContent = error.message;
            }
        }
        wireToolbar();
        load();

        function wireToolbar() {
            document.getElementById('storeSearch').addEventListener('input', function (e) {
                searchTerm = e.target.value;
                render();
            });
            document.getElementById('filterChips').addEventListener('click', function (e) {
                const chip = e.target.closest('button[data-filter]');
                if (!chip) return;
                activeFilter = chip.getAttribute('data-filter');
                paintChips();
                render();
            });
            paintChips();
        }

        function paintChips() {
            const chips = document.querySelectorAll('#filterChips button[data-filter]');
            Array.prototype.forEach.call(chips, function (chip) {
                const on = chip.getAttribute('data-filter') === activeFilter;
                chip.className = 'lv-btn lv-btn-sm ' + (on ? 'lv-btn-primary' : 'lv-btn-outline');
            });
        }

        function render() {
            const q = searchTerm.trim().toLowerCase();
            let list = stores.filter(function (s) {
                if (q) {
                    const inName = String(s.name || '').toLowerCase().indexOf(q) > -1;
                    const inAddr = String(s.address || '').toLowerCase().indexOf(q) > -1;
                    if (!inName && !inAddr) return false;
                }
                if (activeFilter === 'featured') return !!s.featured;
                if (activeFilter === 'free') return !!s.freeDelivery;
                return true;
            });
            if (activeFilter === 'cheapest') {
                list = list.slice().sort(function (a, b) { return a.prices.small - b.prices.small; });
            }
            document.getElementById('resultCount').textContent = list.length + (list.length === 1 ? ' store found' : ' stores found');
            document.getElementById('storeGrid').innerHTML = list.map(storeCard).join('') ||
                '<div class="lv-card lv-empty"><i class="fa-solid fa-store"></i><h3>No stores found</h3><p>Try a different search or filter.</p></div>';
        }

        function badgesFor(s) {
            return (s.featured ? '<span class="lv-badge lv-tone-pink"><i class="fa-solid fa-star"></i>Featured</span>' : '') +
                   (s.freeDelivery ? '<span class="lv-badge lv-tone-green"><i class="fa-solid fa-truck-fast"></i>Free delivery</span>' : '') +
                   (!s.accepting ? '<span class="lv-badge lv-tone-amber"><i class="fa-solid fa-pause"></i>Not accepting orders</span>' : '');
        }

        function storeCard(s) {
            const banner = s.banner
                ? '<img class="lv-media lv-banner" src="' + LV.esc(imgUrl(s.banner)) + '" alt="' + LV.esc(s.name) + '" onerror="' + IMG_FALLBACK + '">'
                : '<div class="lv-media lv-banner lv-fallback"></div>';
            const badges = badgesFor(s);
            const distLine = s.distanceKm != null
                ? '<div class="lv-muted" style="font-size:12.5px; margin-bottom:12px;"><i class="fa-solid fa-location-dot"></i> ' + s.distanceKm.toFixed(1) + ' km away</div>'
                : '<div class="lv-muted" style="font-size:12.5px; margin-bottom:12px;"><i class="fa-solid fa-location-dot"></i> Pin your address in My Profile to see the distance</div>';
            const priceRow = '<div style="border-top:1px solid var(--lv-line); padding-top:12px; font-size:13px; font-weight:800; color:var(--lv-plum);">' +
                'Small ' + LV.peso(s.prices.small) + ' <span class="lv-muted" style="font-weight:600;">(5kg)</span>' +
                ' &middot; Medium ' + LV.peso(s.prices.medium) + ' <span class="lv-muted" style="font-weight:600;">(10kg)</span>' +
                ' &middot; Large ' + LV.peso(s.prices.large) + ' <span class="lv-muted" style="font-weight:600;">(15kg)</span></div>';
            return '<a class="lv-card lv-card-click" href="Customer_View_Store_Page.php?id=' + s.id + '" style="overflow:hidden; display:block;">' +
                banner +
                '<div class="lv-card-pad">' +
                    (badges ? '<div class="lv-wrap" style="gap:6px; margin-bottom:10px;">' + badges + '</div>' : '') +
                    '<h3 style="font-size:17px; color:var(--lv-plum); margin-bottom:4px;">' + LV.esc(s.name) + '</h3>' +
                    '<p class="lv-muted" style="font-size:13px; margin-bottom:8px;">' + LV.esc(s.address || 'Address to be confirmed') + '</p>' +
                    distLine + priceRow +
                '</div></a>';
        }
    </script>
</body>

</html>
