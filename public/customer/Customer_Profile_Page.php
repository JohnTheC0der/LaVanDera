<?php
/**
 * Customer Profile page (converted to PHP) with the map pin that unlocks delivery fees.
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
    <title>My Profile - LaVanDera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/lavandera_theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
    <style>
        /* map picker (same look as the Store Setup page) */
        .setup-map { height: 280px; border-radius: 14px; border: 1px solid var(--lv-line, #e6d5df); z-index: 0; }
        .setup-map-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin: 8px 0; }
    </style>
</head>

<body>
    <div id="lv-nav"></div>

    <main class="lv-container">
        <div class="lv-page-head">
            <div class="lv-eyebrow">Account</div>
            <h1 class="lv-title">My Profile</h1>
            <p class="lv-sub">Keep your contact number and address current - riders use them to pick up and drop off
                your laundry.</p>
        </div>

        <div class="lv-grid lv-grid-2" style="align-items:start;">

            <!-- ACCOUNT SUMMARY -->
            <section class="lv-card lv-card-pad">
                <div class="lv-row" style="gap:16px; margin-bottom:18px;">
                    <div class="lv-avatar lv-avatar-lg" id="pfpAvatar">?</div>
                    <div>
                        <h2 style="font-size:22px; color:var(--lv-plum);" id="pfpName">&nbsp;</h2>
                        <div class="lv-muted" style="font-size:14px;" id="pfpEmail">&nbsp;</div>
                        <span class="lv-badge lv-tone-pink" style="margin-top:7px;">
                            <i class="fa-solid fa-user"></i>Customer
                        </span>
                    </div>
                </div>

                <div class="lv-kv"><span>Member since</span><span id="pfpSince">&nbsp;</span></div>
                <div class="lv-kv"><span>Total orders</span><span id="pfpOrders">0</span></div>
                <div class="lv-kv"><span>Completed orders</span><span id="pfpDone">0</span></div>
                <div class="lv-kv"><span>Lifetime spend</span><span id="pfpSpent">&nbsp;</span></div>

                <hr class="lv-divider">
                <a href="Customer_My_Orders_Page.php" class="lv-btn lv-btn-outline lv-btn-sm">
                    <i class="fa-solid fa-basket-shopping"></i> View my orders
                </a>
            </section>

            <!-- EDIT CONTACT DETAILS -->
            <section class="lv-card lv-card-pad">
                <div class="lv-eyebrow">Editable details</div>
                <h2 class="lv-section-title" style="margin-bottom:16px;">Contact &amp; Address</h2>

                <form onsubmit="saveProfile(event)" novalidate>
                    <div class="lv-field">
                        <label class="lv-label" for="pfName">Full Name</label>
                        <input class="lv-input" type="text" id="pfName" maxlength="120" placeholder="Your full name">
                    </div>
                    <div class="lv-field">
                        <label class="lv-label" for="pfContact">Contact Number</label>
                        <input class="lv-input" type="tel" id="pfContact" maxlength="20" placeholder="09XX XXX XXXX">
                    </div>
                    <div class="lv-field">
                        <label class="lv-label" for="pfAddress">Pickup / Delivery Address</label>
                        <textarea class="lv-textarea" id="pfAddress" maxlength="500"
                            placeholder="Unit / street, barangay, city"></textarea>
                        <p class="lv-form-note" style="margin-top:6px;">
                            <i class="fa-solid fa-circle-info" style="color:var(--lv-pink);"></i>
                            New orders will use this address for distance-based rider fees.
                        </p>
                    </div>

                    <div class="lv-field">
                        <label class="lv-label">Pin your address on the map (used to calculate delivery fees)</label>
                        <div class="setup-map-bar">
                            <button class="lv-btn lv-btn-outline lv-btn-sm" type="button" id="findOnMap"><i class="fa-solid fa-location-dot"></i> Find address on map</button>
                            <span id="mapStatus" class="lv-sub" style="max-width:none;"></span>
                        </div>
                        <div id="profileMap" class="setup-map"></div>
                        <input type="hidden" id="pfLat">
                        <input type="hidden" id="pfLng">
                    </div>

                    <p class="lv-error" id="pfError"></p>

                    <button type="submit" class="lv-btn lv-btn-primary lv-btn-block">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                </form>
            </section>

        </div>

        <!-- RECENT ORDERS -->
        <section class="lv-mt">
            <div class="lv-row-between" style="margin-bottom:16px;">
                <div>
                    <div class="lv-eyebrow">Account activity</div>
                    <h2 class="lv-section-title">Recent Orders</h2>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="../js/lavandera_map.js"></script>
    <script>
        // The SERVER already checked who you are (guard_customer_page in the PHP block at the top).
        // window.LV_SESSION is the real signed-in user, replacing the old demo profile (Maria).
        const me = window.LV_SESSION;
        try { LV.renderNav('customer', null); LVApi.fixNavLinks(); LVLinks.fix(); } catch (navError) { console.warn('Navbar failed to render', navError); }

        // Images are saved in the database as paths relative to public/ (e.g. uploads/stores/x.jpg); pages live one folder deeper.
        const imgUrl = function (path) { return path ? '../' + path : ''; };
        const IMG_FALLBACK = "this.onerror=null; this.className='lv-media lv-banner lv-fallback'; this.removeAttribute('src');";

        let customer = null;
        let orders = [];
        let stats = null;
        let mapReady = false;

        async function load() {
            try {
                const replies = await Promise.all([
                    LVApi.get('../api/customer/profile.php'),
                    LVApi.get('../api/customer/orders.php')
                ]);
                customer = replies[0].customer;
                stats = replies[0].stats;
                orders = replies[1].orders;
                render();
            } catch (error) {
                document.getElementById('pfError').textContent = error.message;
            }
        }
        load();

        function render() {
            document.getElementById('pfpAvatar').textContent = LV.initials(customer.name);
            document.getElementById('pfpName').textContent = customer.name;
            document.getElementById('pfpEmail').textContent = customer.email;

            // "Member since" is the day the account was created (users.created_at), not the first order.
            document.getElementById('pfpSince').textContent =
                new Date(customer.joined).toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
            document.getElementById('pfpOrders').textContent = stats.total;
            document.getElementById('pfpDone').textContent = stats.completed;
            document.getElementById('pfpSpent').textContent = LV.peso(stats.spent);

            document.getElementById('pfName').value = customer.name || '';
            document.getElementById('pfContact').value = customer.contact || '';
            document.getElementById('pfAddress').value = customer.address || '';
            document.getElementById('pfLat').value = customer.latitude == null ? '' : customer.latitude;
            document.getElementById('pfLng').value = customer.longitude == null ? '' : customer.longitude;

            // Start the map ONCE, after we know the saved pin (LVMap comes from lavandera_map.js, written by your groupmate).
            if (!mapReady) {
                mapReady = true;
                LVMap.picker({
                    mapEl: document.getElementById('profileMap'), address: document.getElementById('pfAddress'),
                    findBtn: document.getElementById('findOnMap'), lat: document.getElementById('pfLat'),
                    lng: document.getElementById('pfLng'), status: document.getElementById('mapStatus'),
                    initial: { lat: document.getElementById('pfLat').value, lng: document.getElementById('pfLng').value }
                });
            }

            const recent = orders.slice(0, 3);
            document.getElementById('recentOrders').innerHTML = recent.length
                ? recent.map(row).join('')
                : '<div class="lv-card lv-empty"><i class="fa-solid fa-basket-shopping"></i><h3>No orders yet</h3><p>Orders you place will show up here.</p>' +
                  '<a href="Customer_Place_Order_Page.php" class="lv-btn lv-btn-primary lv-btn-sm" style="margin-top:14px;">Place an Order</a></div>';
        }

        function row(o) {
            const bag = LV.bagOf(o.bag);
            return '<a class="lv-card lv-card-pad lv-row-between lv-card-click" href="Customer_My_Orders_Page.php?order=' + o.id + '" style="display:flex;">' +
                '<div class="lv-row"><div class="lv-avatar"><i class="fa-solid ' + bag.icon + '" style="font-size:18px;"></i></div>' +
                '<div><div style="font-weight:800; color:var(--lv-plum);">' + LV.esc(o.storeName || 'Store') + '</div>' +
                '<div class="lv-muted" style="font-size:13px;">#' + o.id + ' &middot; ' + bag.name + ' (' + bag.kg + 'kg) &middot; ' + LV.when(o.createdAt) + '</div></div></div>' +
                '<div class="lv-row" style="gap:16px;"><span style="font-weight:800;">' + LV.peso(o.total) + '</span>' + LV.statusBadge(o.status) + '</div></a>';
        }

        async function saveProfile(event) {
            event.preventDefault();
            const errBox = document.getElementById('pfError');
            errBox.textContent = '';
            const name = document.getElementById('pfName').value.trim();
            const contact = document.getElementById('pfContact').value.trim();
            const address = document.getElementById('pfAddress').value.trim();
            const latitude = document.getElementById('pfLat').value;
            const longitude = document.getElementById('pfLng').value;

            // Quick checks for a friendly message; the SERVER repeats every check (never trust the browser).
            if (!name) { errBox.textContent = 'Please enter your full name.'; return; }
            if (!contact || !address) { errBox.textContent = 'Contact number and address are both required.'; return; }
            if (!/^[0-9+\-\s()]{7,20}$/.test(contact)) { errBox.textContent = 'Please enter a valid contact number.'; return; }
            if (latitude === '' || longitude === '') { errBox.textContent = 'Click "Find address on map" (or drag the pin) so we know where to pick up.'; return; }

            try {
                const result = await LVApi.postJson('../api/customer/profile.php', {
                    name: name, contact: contact, address: address, latitude: latitude, longitude: longitude
                });
                customer = result.customer;
                stats = result.stats;
                // The navbar shows the name: keep the browser copy of the session in step with the database.
                try { window.LV_SESSION.name = customer.name; localStorage.setItem('lv_session', JSON.stringify(window.LV_SESSION)); LV.renderNav('customer', null); LVApi.fixNavLinks(); LVLinks.fix(); } catch (e) { /* ignore */ }
                LV.toast('Profile updated successfully.');
                render();
            } catch (error) {
                errBox.textContent = error.message;
            }
        }
    </script>
</body>

</html>
