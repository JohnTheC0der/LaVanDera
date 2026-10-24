<?php
/**
 * Customer View Store page (converted to PHP).
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
    <title>Store Details - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">
        <p class="lv-error" id="storeError" role="alert"></p>

        <!-- BACK LINK -->
        <div style="padding-top:24px;">
            <a class="lv-link" href="Customer_Stores_Page.php" style="font-size:14px;">
                <i class="fa-solid fa-arrow-left" style="font-size:12px;"></i> Back to all stores</a>
        </div>

        <!-- HERO BANNER -->
        <div id="storeBanner" style="height:220px; border-radius:28px; overflow:hidden; margin-top:14px;"></div>

        <!-- STORE HEADER (overlaps the banner) -->
        <div class="lv-card lv-card-pad" id="storeHead" style="margin-top:-56px; position:relative; z-index:1;"></div>

        <!-- BAG PRICING -->
        <section class="lv-mt" id="pricingSection" style="display:none;">
            <div class="lv-eyebrow">Simple per-bag rates</div>
            <h2 class="lv-section-title" style="margin-bottom:16px;">Bag Pricing</h2>
            <div class="lv-grid lv-grid-3" id="priceGrid"></div>
        </section>

        <!-- BENEFITS -->
        <section class="lv-mt" id="benefitsSection" style="display:none;">
            <div class="lv-eyebrow">Extra perks</div>
            <h2 class="lv-section-title" style="margin-bottom:16px;">Store Benefits</h2>
            <div class="lv-card lv-card-pad" id="benefitsCard"></div>
        </section>

        <!-- BOTTOM CTA -->
        <section class="lv-hero lv-mt" id="ctaSection"></section>

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

        let storeRec = null;
        let feeRule = null;

        async function load() {
            try {
                const data = await LVApi.get('../api/customer/stores.php');
                const id = Number(new URLSearchParams(location.search).get('id'));
                storeRec = data.stores.filter(function (s) { return s.id === id; })[0] || null;
                feeRule = data.feeRule;
                if (!storeRec) { location.replace('Customer_Stores_Page.php'); return; }   // unknown store: back to the directory
                render();
            } catch (error) {
                document.getElementById('storeError').textContent = error.message;
            }
        }
        load();

        function render() {
            const s = storeRec;
            const km = s.distanceKm;

            /* ---- hero banner ---- */
            document.getElementById('storeBanner').innerHTML = s.banner
                ? '<img class="lv-media" style="height:100%; object-fit:cover;" src="' + LV.esc(imgUrl(s.banner)) + '" alt="' + LV.esc(s.name) + '" ' +
                  'onerror="this.onerror=null; this.className=\'lv-media lv-fallback\'; this.removeAttribute(\'src\');">'
                : '<div class="lv-media lv-fallback" style="height:100%;"></div>';

            /* ---- header card ---- */
            const logo = s.image
                ? '<img id="storeLogo" src="' + LV.esc(imgUrl(s.image)) + '" alt="' + LV.esc(s.name) + '" ' +
                  'style="width:84px; height:84px; border-radius:22px; object-fit:cover; border:3px solid #fff; box-shadow:var(--lv-shadow); flex-shrink:0;" ' +
                  'onerror="this.onerror=null; this.style.display=\'none\'; var f=document.getElementById(\'storeAvatar\'); if(f) f.style.display=\'grid\';">' +
                  '<div class="lv-avatar lv-avatar-lg" id="storeAvatar" style="display:none;">' + LV.esc(LV.initials(s.name)) + '</div>'
                : '<div class="lv-avatar lv-avatar-lg">' + LV.esc(LV.initials(s.name)) + '</div>';

            const cta = s.accepting
                ? '<a class="lv-btn lv-btn-primary" href="Customer_Place_Order_Page.php?store=' + s.id + '"><i class="fa-solid fa-plus"></i> Place Order</a>'
                : '<span class="lv-btn lv-btn-primary" style="opacity:.5; cursor:not-allowed;">Place Order</span>';

            const statusBadge = s.accepting
                ? '<span class="lv-badge lv-tone-green"><i class="fa-solid fa-circle-check"></i>Accepting orders</span>'
                : '<span class="lv-badge lv-tone-amber"><i class="fa-solid fa-pause"></i>Paused</span>';

            const distanceRow = km != null
                ? '<div class="lv-kv"><span>Distance from you</span><span>' + km.toFixed(1) + ' km &middot; ' +
                  (s.freeDelivery ? 'free delivery' : 'est. rider fee ' + LV.peso(s.deliveryFee) + ' each way') + '</span></div>'
                : '<div class="lv-kv"><span>Distance from you</span><span>Pin your address in <a class="lv-link" href="Customer_Profile_Page.php">My Profile</a></span></div>';

            document.getElementById('storeHead').innerHTML =
                '<div class="lv-row" style="gap:18px; align-items:center; flex-wrap:wrap;">' + logo +
                    '<div style="flex:1; min-width:230px;">' +
                        '<h1 class="lv-title" style="font-size:clamp(22px,3vw,30px);">' + LV.esc(s.name) + '</h1>' +
                        '<div class="lv-wrap" style="gap:6px; margin-top:8px;">' + badgesFor(s) + '</div>' +
                    '</div>' + cta +
                '</div>' +
                (s.description ? '<p class="lv-muted" style="margin-top:14px; font-size:14.5px; line-height:1.6;">' + LV.esc(s.description) + '</p>' : '') +
                '<hr class="lv-divider">' +
                '<div class="lv-kv"><span>Address</span><span>' + LV.esc(s.address || 'To be confirmed') + '</span></div>' +
                '<div class="lv-kv"><span>Contact number</span><span>' + LV.esc(s.contact || 'To be confirmed') + '</span></div>' +
                distanceRow +
                '<div class="lv-kv"><span>Operating status</span><span>' + statusBadge + '</span></div>';

            /* ---- bag pricing ---- */
            document.getElementById('pricingSection').style.display = '';
            document.getElementById('priceGrid').innerHTML = LV.bags.map(function (b) {
                return '<div class="lv-card lv-card-pad" style="text-align:center;">' +
                    '<div class="lv-stat-icon"><i class="fa-solid ' + b.icon + '"></i></div>' +
                    '<div style="font-weight:800; color:var(--lv-plum); font-size:16px;">' + b.name + '</div>' +
                    '<div class="lv-muted" style="font-size:12.5px; margin-top:3px;">' + b.kg + ' KG capacity</div>' +
                    '<div style="margin-top:12px; font-weight:800; font-size:30px; line-height:1; color:var(--lv-pink);">' + LV.peso(s.prices[b.code]) + '</div>' +
                    '<div class="lv-muted" style="font-size:12.5px; margin-top:8px;">includes wash, dry &amp; fold</div></div>';
            }).join('');

            /* ---- benefits ---- */
            if (s.freeDelivery) {
                document.getElementById('benefitsSection').style.display = '';
                document.getElementById('benefitsCard').innerHTML =
                    '<div class="lv-row-between" style="align-items:flex-start; padding:6px 0;"><div>' +
                    '<div style="font-weight:800; color:var(--lv-plum); font-size:15px;">Free delivery</div>' +
                    '<div class="lv-muted" style="font-size:13.5px; margin-top:2px;">Customer does not pay the send/return rider fees.</div></div>' +
                    '<span class="lv-badge lv-tone-green"><i class="fa-solid fa-circle-check"></i>Included</span></div>';
            }

            /* ---- bottom CTA ---- */
            document.getElementById('ctaSection').innerHTML =
                '<h1>Ready to get your laundry done?</h1>' +
                '<p>Send your bags over to ' + LV.esc(s.name) + ' - we pick them up, then bring them back fresh and folded.</p>' +
                '<div class="lv-hero-cta"><a class="lv-btn lv-btn-ghost" href="Customer_Place_Order_Page.php?store=' + s.id + '">' +
                '<i class="fa-solid fa-plus"></i> Place Order at ' + LV.esc(s.name) + '</a></div>';
        }

        function badgesFor(s) {
            return (s.featured ? '<span class="lv-badge lv-tone-pink"><i class="fa-solid fa-star"></i>Featured</span>' : '') +
                   (s.freeDelivery ? '<span class="lv-badge lv-tone-green"><i class="fa-solid fa-truck-fast"></i>Free delivery</span>' : '') +
                   (!s.accepting ? '<span class="lv-badge lv-tone-amber"><i class="fa-solid fa-pause"></i>Not accepting orders</span>' : '');
        }
    </script>
</body>

</html>
