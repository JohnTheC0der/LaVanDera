/* =====================================================================
   LaVanDera - demo data + shared helpers (frontend prototype layer)
   Everything here is stored in-memory / localStorage.  When the PHP +
   MySQL backend lands, only this file's accessors get rewritten to
   fetch() real endpoints - page code stays the same.
   ===================================================================== */

const LV = (function () {
    'use strict';

    /* -------------------------------------------------- settings ---- */
    const SETTINGS = {
        baseDeliveryFee: 40,   // PHP covering the first baseDistanceKm
        baseDistanceKm: 2,
        perKmFee: 10           // PHP per extra km
    };

    const BAG_TYPES = [
        { id: 1, code: 'small',  name: 'Small Bag',  kg: 5,  icon: 'fa-bag-shopping' },
        { id: 2, code: 'medium', name: 'Medium Bag', kg: 10, icon: 'fa-box' },
        { id: 3, code: 'large',  name: 'Large Bag',  kg: 15, icon: 'fa-boxes-stacked' }
    ];

    const IMG = {
        wash1: 'https://images.unsplash.com/photo-1545173168-9f1947eebb7f?w=900&q=80',
        wash2: 'https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=900&q=80',
        wash3: 'https://images.unsplash.com/photo-1521656693074-0ef32e80a5d5?w=900&q=80',
        wash4: 'https://images.unsplash.com/photo-1582735689369-4fe89db7114c?w=900&q=80',
        wash5: 'https://images.unsplash.com/photo-1610557892470-55d9e80c0bce?w=900&q=80',
        wash6: 'https://images.unsplash.com/photo-1604176354204-9268737828e4?w=900&q=80'
    };

    /* ------------------------------------------------- seed data ---- */
    /* Mirrors lavandera_sample_data.sql, extended so the demo pages have
       one order sitting in every status of the flow.                        */
    const SEED = {
        admin: { id: 1, name: 'LaVanDera Admin', email: 'admin@lavandera.test', role: 'admin' },

        customers: [
            { id: 2,  name: 'Maria Santos',    email: 'maria.santos@example.com',  contact: '09171234567', address: '12 Shaw Blvd, Kapitolyo, Pasig City', lat: 14.5724, lng: 121.0565 },
            { id: 3,  name: 'Juan Dela Cruz',  email: 'juan.delacruz@example.com', contact: '09181234567', address: '45 Sandoval Ave, Pinagbuhatan, Pasig City', lat: 14.5590, lng: 121.0970 },
            { id: 9,  name: 'Liza Cruz',       email: 'liza.cruz@example.com',     contact: '09175551234', address: '19 Unit C, Captain Jose St, Kapitolyo, Pasig City', lat: 14.5747, lng: 121.0645 },
            { id: 10, name: 'Mark Reyes',      email: 'mark.reyes@example.com',    contact: '09167778888', address: '5 A. Mabini St, Rosario, Pasig City', lat: 14.5661, lng: 121.0806 }
        ],

        riders: [
            { id: 4,  name: 'Rico Reyes',  email: 'rico.rider@example.com', contact: '09191234567', status: 'active',           joined: '2026-08-14' },
            { id: 5,  name: 'Ana Lopez',   email: 'ana.rider@example.com',  contact: '09201234567', status: 'pending_approval', joined: '2026-10-19' },
            { id: 11, name: 'Ben Ramos',   email: 'ben.rider@example.com',  contact: '09219998877', status: 'pending_approval', joined: '2026-10-21' }
        ],

        /* status: active | setup_required | pending_approval | rejected */
        stores: [
            {
                id: 6, name: 'Kapitolyo Wash Hub', email: 'kapitolyo.wash@example.com', contact: '09211234567',
                address: '88 Brixton St, Pasig Palengke, Pasig City', lat: 14.5764, lng: 121.0851,
                description: 'Neighborhood laundromat with same-day wash-and-fold and careful sorting of whites and colors.',
                prices: { small: 50, medium: 90, large: 130 }, benefits: [], featured: true, accepting: true,
                status: 'active', rating: 4.8, image: IMG.wash1, banner: IMG.wash2, joined: '2026-08-02'
            },
            {
                id: 7, name: 'Bubbles Laundry Pinagbuhatan', email: 'bubbles.laundry@example.com', contact: '09221234567',
                address: '7 Caruncho Ext, Pinagbuhatan, Pasig City', lat: 14.5700, lng: 121.0900,
                description: 'Affordable per-load laundry with free pickup and delivery within Pasig.',
                prices: { small: 45, medium: 80, large: 115 }, benefits: ['Free delivery'], featured: true, accepting: true,
                status: 'active', rating: 4.6, image: IMG.wash3, banner: IMG.wash4, joined: '2026-08-20'
            },
            {
                id: 12, name: 'Sampaguita Wash & Fold', email: 'sampaguita.wash@example.com', contact: '09173334455',
                address: '22 Kabayo St, Kapitolyo, Pasig City', lat: 14.5738, lng: 121.0620,
                description: 'Small boutique wash-and-fold. Fragrance options, hypoallergenic detergent on request.',
                prices: { small: 55, medium: 95, large: 135 }, benefits: [], featured: false, accepting: true,
                status: 'active', rating: 4.4, image: IMG.wash5, banner: IMG.wash6, joined: '2026-09-05'
            },
            {
                id: 13, name: 'Ortigas Laundry Hub', email: 'ortigas.hub@example.com', contact: '09184445566',
                address: '5F San Miguel Ave, Ortigas Center, Pasig City', lat: 14.5805, lng: 121.0619,
                description: 'Express service for condo dwellers - wash, dry, and fold in under 4 hours.',
                prices: { small: 60, medium: 110, large: 150 }, benefits: [], featured: false, accepting: true,
                status: 'active', rating: 4.9, image: IMG.wash2, banner: IMG.wash3, joined: '2026-09-18'
            },
            {
                id: 14, name: 'Fresh Fold Express', email: 'newstore.laundry@example.com', contact: '09231234567',
                address: '3 Ortigas Ave Ext, Pasig City', lat: 14.5850, lng: 121.0750,
                description: 'Newly set up store awaiting admin review.',
                prices: { small: 55, medium: 95, large: 135 }, benefits: [], featured: false, accepting: true,
                status: 'pending_approval', rating: 0, image: null, banner: null, joined: '2026-10-20'
            },
            {
                id: 15, name: 'Spin City Laundry', email: 'spincity@example.com', contact: '09271112233',
                address: null, lat: null, lng: null,
                description: null,
                prices: null, benefits: [], featured: false, accepting: false,
                status: 'setup_required', rating: 0, image: null, banner: null, joined: '2026-10-22'
            }
        ],

        /* fee snapshots follow the seeded rule: 40 + 10 per km past 2 km */
        orders: [
            {
                id: 1001, customerId: 2, storeId: 6, bag: 'medium', payment: 'gcash', paymentStatus: 'paid',
                storeFee: 90, sendFee: 54, returnFee: 54, total: 198, freeDelivery: false, distanceKm: 3.4,
                status: 'completed', createdAt: daysAgo(6), completedAt: daysAgo(4),
                history: hist([
                    ['pending_store', 6, 'Order placed'], ['waiting_for_driver', 6, 'Store confirmed order'],
                    ['enroute_to_store', 5, 'Rider accepted send job'], ['at_laundromat', 5, 'Store confirmed delivery'],
                    ['waiting_for_return_driver', 5, 'Store marked ready to return'], ['enroute_to_customer', 4, 'Rider accepted return job'],
                    ['completed', 4, 'Customer confirmed return']
                ])
            },
            {
                id: 1002, customerId: 3, storeId: 7, bag: 'small', payment: 'cash', paymentStatus: 'paid',
                storeFee: 45, sendFee: 0, returnFee: 0, total: 45, freeDelivery: true, distanceKm: 1.8,
                status: 'at_laundromat', createdAt: daysAgo(2), completedAt: null,
                history: hist([
                    ['pending_store', 2, 'Order placed'], ['waiting_for_driver', 2, 'Store confirmed order'],
                    ['enroute_to_store', 2, 'Rider accepted send job'], ['at_laundromat', 1, 'Store confirmed delivery']
                ])
            },
            {
                id: 1003, customerId: 2, storeId: 6, bag: 'large', payment: 'cash', paymentStatus: 'unpaid',
                storeFee: 130, sendFee: 54, returnFee: 54, total: 238, freeDelivery: false, distanceKm: 3.4,
                status: 'waiting_for_driver', createdAt: hoursAgo(3), completedAt: null,
                history: hist([['pending_store', 3, 'Order placed'], ['waiting_for_driver', 3, 'Store confirmed order']])
            },
            {
                id: 1004, customerId: 3, storeId: 6, bag: 'small', payment: 'gcash', paymentStatus: 'paid',
                storeFee: 50, sendFee: 49, returnFee: 49, total: 148, freeDelivery: false, distanceKm: 2.9,
                status: 'pending_store', createdAt: hoursAgo(1), completedAt: null,
                history: hist([['pending_store', 3, 'Order placed']])
            },
            {
                id: 1005, customerId: 2, storeId: 7, bag: 'medium', payment: 'cash', paymentStatus: 'unpaid',
                storeFee: 80, sendFee: 0, returnFee: 0, total: 80, freeDelivery: true, distanceKm: 2.4,
                status: 'enroute_to_store', createdAt: hoursAgo(5), completedAt: null,
                history: hist([
                    ['pending_store', 5, 'Order placed'], ['waiting_for_driver', 5, 'Store confirmed order'],
                    ['enroute_to_store', 4, 'Rider accepted send job']
                ])
            },
            {
                id: 1006, customerId: 2, storeId: 13, bag: 'large', payment: 'gcash', paymentStatus: 'paid',
                storeFee: 150, sendFee: 50, returnFee: 50, total: 250, freeDelivery: false, distanceKm: 3.0,
                status: 'waiting_for_return_driver', createdAt: daysAgo(1), completedAt: null,
                history: hist([
                    ['pending_store', 5, 'Order placed'], ['waiting_for_driver', 5, 'Store confirmed order'],
                    ['enroute_to_store', 5, 'Rider accepted send job'], ['at_laundromat', 4, 'Store confirmed delivery'],
                    ['waiting_for_return_driver', 1, 'Store marked ready to return']
                ])
            },
            {
                id: 1007, customerId: 2, storeId: 12, bag: 'small', payment: 'cash', paymentStatus: 'unpaid',
                storeFee: 55, sendFee: 44, returnFee: 44, total: 143, freeDelivery: false, distanceKm: 2.4,
                status: 'enroute_to_customer', createdAt: daysAgo(1), completedAt: null,
                history: hist([
                    ['pending_store', 5, 'Order placed'], ['waiting_for_driver', 5, 'Store confirmed order'],
                    ['enroute_to_store', 4, 'Rider accepted send job'], ['at_laundromat', 3, 'Store confirmed delivery'],
                    ['waiting_for_return_driver', 2, 'Store marked ready to return'], ['enroute_to_customer', 1, 'Rider accepted return job']
                ])
            },
            {
                id: 1008, customerId: 3, storeId: 7, bag: 'large', payment: 'gcash', paymentStatus: 'paid',
                storeFee: 115, sendFee: 0, returnFee: 0, total: 115, freeDelivery: true, distanceKm: 3.8,
                status: 'rejected', createdAt: daysAgo(3), completedAt: null,
                storeRejectionReason: 'All washing machines under maintenance today.',
                history: hist([['pending_store', 3, 'Order placed'], ['rejected', 3, 'Store rejected order']])
            }
        ]
    };

    /* --------------------------------------------------- helpers ---- */
    function daysAgo(d) { const t = new Date(); t.setDate(t.getDate() - d); return t.toISOString(); }
    function hoursAgo(h) { const t = new Date(); t.setHours(t.getHours() - h); return t.toISOString(); }

    /* rows: [status, userId, note] spaced one hour apart ending "now" */
    function hist(rows) {
        const now = Date.now();
        return rows.map(function (r, i) {
            return { status: r[0], by: r[1], note: r[2], at: new Date(now - (rows.length - i) * 36e5).toISOString() };
        });
    }

    const peso = function (n) {
        const v = Number(n || 0);
        return '\u20b1' + v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    function bagOf(code) { return BAG_TYPES.find(function (b) { return b.code === code; }) || BAG_TYPES[0]; }

    function deliveryFee(km) {
        const extra = Math.max(0, Number(km) - SETTINGS.baseDistanceKm);
        return Math.round(SETTINGS.baseDeliveryFee + SETTINGS.perKmFee * extra);
    }

    function distanceKm(aLat, aLng, bLat, bLng) {
        if (aLat == null || bLat == null) return 0;
        const R = 6371, toRad = function (d) { return d * Math.PI / 180; };
        const dLat = toRad(bLat - aLat), dLng = toRad(bLng - aLng);
        const s = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * Math.sin(dLng / 2) ** 2;
        return Math.round(2 * R * Math.asin(Math.sqrt(s)) * 10) / 10;
    }

    const STATUS = {
        pending_store:            { label: 'Waiting for store',            tone: 'amber', icon: 'fa-hourglass-half' },
        waiting_for_driver:       { label: 'Waiting for driver',           tone: 'amber', icon: 'fa-hourglass-half' },
        enroute_to_store:         { label: 'Enroute to store',             tone: 'blue',  icon: 'fa-motorcycle' },
        at_laundromat:            { label: 'At laundromat',                tone: 'green', icon: 'fa-building' },
        waiting_for_return_driver:{ label: 'Waiting for return rider',     tone: 'amber', icon: 'fa-hourglass-half' },
        enroute_to_customer:      { label: 'Enroute to you',               tone: 'blue',  icon: 'fa-motorcycle' },
        completed:                { label: 'Completed',                    tone: 'green', icon: 'fa-circle-check' },
        rejected:                 { label: 'Rejected by store',            tone: 'red',   icon: 'fa-circle-xmark' },
        cancelled:                { label: 'Cancelled',                    tone: 'gray',  icon: 'fa-ban' }
    };

    const FLOW = ['pending_store', 'waiting_for_driver', 'enroute_to_store', 'at_laundromat',
                  'waiting_for_return_driver', 'enroute_to_customer', 'completed'];

    const USER_STATUS = {
        active:           { label: 'Active',           tone: 'green' },
        setup_required:   { label: 'Setup required',   tone: 'amber' },
        pending_approval: { label: 'Pending approval', tone: 'amber' },
        rejected:         { label: 'Rejected',         tone: 'red' },
        suspended:        { label: 'Suspended',        tone: 'gray' }
    };

    function statusBadge(code) {
        const s = STATUS[code] || STATUS.cancelled;
        return '<span class="lv-badge lv-tone-' + s.tone + '"><i class="fa-solid ' + s.icon + '"></i>' + s.label + '</span>';
    }

    function userStatusBadge(code) {
        const s = USER_STATUS[code] || USER_STATUS.suspended;
        return '<span class="lv-badge lv-tone-' + s.tone + '">' + s.label + '</span>';
    }

    function initials(name) {
        return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (p) { return p[0]; }).join('').toUpperCase();
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function when(iso) {
        if (!iso) return '-';
        const d = new Date(iso), now = new Date(), diff = (now - d) / 1000;
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400 && d.getDate() === now.getDate()) return d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
        return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    /* ------------------------------------------- local persistence -- */
    function read(key, fallback) {
        try { return JSON.parse(localStorage.getItem(key)) || fallback; } catch (e) { return fallback; }
    }
    function write(key, val) { localStorage.setItem(key, JSON.stringify(val)); }

    const K = { session: 'lv_session', extraCustomers: 'lv_extra_customers', extraOrders: 'lv_extra_orders',
                orderState: 'lv_order_state', userState: 'lv_user_state' };

    /* ------------------------------------------------- accessors ---- */
    function customers() {
        return SEED.customers.concat(read(K.extraCustomers, []))
            .map(function (c) { return Object.assign({}, c, read(K.userState, {})[c.id] || {}); });
    }
    function riders() {
        return SEED.riders.map(function (r) { return Object.assign({}, r, read(K.userState, {})[r.id] || {}); });
    }
    function stores() {
        return SEED.stores.map(function (s) { return Object.assign({}, s, read(K.userState, {})[s.id] || {}); });
    }
    function orders() {
        const state = read(K.orderState, {});
        return SEED.orders.concat(read(K.extraOrders, []))
            .map(function (o) { return Object.assign({}, o, state[o.id] || {}); })
            .sort(function (a, b) { return new Date(b.createdAt) - new Date(a.createdAt); });
    }

    function customer(id) { return customers().find(function (c) { return c.id === Number(id); }); }
    function store(id) { return stores().find(function (s) { return s.id === Number(id); }); }
    function order(id) { return orders().find(function (o) { return o.id === Number(id); }); }

    function publicStores() {
        return stores().filter(function (s) { return s.status === 'active'; });
    }
    function ordersOfCustomer(id) {
        return orders().filter(function (o) { return o.customerId === Number(id); });
    }

    /* ------------------------------------------------ mutations ----- */
    function addCustomer(data) {
        const list = read(K.extraCustomers, []);
        const id = 100 + list.length + 1;
        const rec = Object.assign({ id: id, lat: null, lng: null }, data);
        list.push(rec); write(K.extraCustomers, list);
        return rec;
    }

    function addOrder(data) {
        const list = read(K.extraOrders, []);
        const id = 2000 + list.length + 1;
        const rec = Object.assign({
            id: id, status: 'pending_store', paymentStatus: data.payment === 'gcash' ? 'paid' : 'unpaid',
            completedAt: null, createdAt: new Date().toISOString(),
            history: [{ status: 'pending_store', by: data.customerId, note: 'Order placed', at: new Date().toISOString() }]
        }, data, { id: id });
        list.push(rec); write(K.extraOrders, list);
        return rec;
    }

    function setOrderStatus(id, status, note, by) {
        const state = read(K.orderState, {});
        const o = order(id); if (!o) return null;
        const patch = Object.assign({}, o);
        patch.status = status;
        patch.history = (o.history || []).concat([{ status: status, by: by || null, note: note || '', at: new Date().toISOString() }]);
        if (status === 'completed') patch.completedAt = new Date().toISOString();
        state[id] = patch; write(K.orderState, state);
        return patch;
    }

    function updateCustomer(id, patch) {
        const state = read(K.userState, {});
        state[id] = Object.assign({}, state[id] || {}, patch);
        write(K.userState, state);
    }

    function setUserStatus(id, status, note) {
        const state = read(K.userState, {});
        state[id] = Object.assign({}, state[id] || {}, { status: status }, note != null ? { statusNote: note } : {});
        write(K.userState, state);
    }

    /* ---------------------------------------------------- session --- */
    function allLogins() {
        const admin = [SEED.admin];
        return admin
            .concat(riders().map(function (r) { return { id: r.id, name: r.name, email: r.email, role: 'rider', status: r.status }; }))
            .concat(stores().map(function (s) { return { id: s.id, name: s.name, email: s.email, role: 'store', status: s.status }; }))
            .concat(customers().map(function (c) { return { id: c.id, name: c.name, email: c.email, role: 'customer', status: 'active' }; }));
    }

    /* Demo password for every seeded account is "password". */
    function login(email, password) {
        const u = allLogins().find(function (x) { return x.email.toLowerCase() === String(email).trim().toLowerCase(); });
        if (!u) return { ok: false, error: 'No account found for that email.' };
        if (String(password) !== 'password') return { ok: false, error: 'Incorrect password. Demo accounts use "password".' };
        if (u.status === 'pending_approval') return { ok: false, error: 'Your account is still pending admin approval.' };
        if (u.status === 'setup_required') return { ok: false, error: 'Please finish your Store Setup first.' };
        if (u.status === 'rejected') return { ok: false, error: 'This account was rejected by the admin.' };
        write(K.session, { id: u.id, role: u.role, name: u.name, email: u.email });
        return { ok: true, user: u };
    }

    function signupCustomer(data) {
        const exists = allLogins().some(function (u) { return u.email.toLowerCase() === String(data.email).toLowerCase(); });
        if (exists) return { ok: false, error: 'That email is already registered.' };
        const rec = addCustomer(data);
        write(K.session, { id: rec.id, role: 'customer', name: rec.name, email: rec.email });
        return { ok: true, user: rec };
    }

    function session() { return read(K.session, null); }
    function logout() { localStorage.removeItem(K.session); location.href = 'Log_In_Page.html'; }

    function requireRole(role, loginPage) {
        const s = session();
        if (!s || s.role !== role) { location.replace(loginPage || 'Log_In_Page.html'); return null; }
        return s;
    }

    /* ------------------------------------------------------ nav ----- */
    const NAV = {
        customer: [
            { key: 'home',    href: 'Customer_Home_Page.html',          label: 'Home',      icon: 'fa-house' },
            { key: 'stores',  href: 'Customer_Stores_Page.html',        label: 'Stores',    icon: 'fa-store' },
            { key: 'orders',  href: 'Customer_My_Orders_Page.html',     label: 'My Orders', icon: 'fa-basket-shopping' },
            { key: 'place',   href: 'Customer_Place_Order_Page.html',   label: 'Place Order', icon: 'fa-plus' }
        ],
        admin: [
            { key: 'dashboard', href: 'Admin_Dashboard_Page.html',  label: 'Dashboard',  icon: 'fa-chart-line' },
            { key: 'customers', href: 'Admin_Customers_Page.html',  label: 'Customers',  icon: 'fa-users' },
            { key: 'riders',    href: 'Admin_Riders_Page.html',     label: 'Riders',     icon: 'fa-motorcycle' },
            { key: 'stores',    href: 'Admin_Stores_Page.html',     label: 'Stores',     icon: 'fa-store' }
        ]
    };

    const DROPDOWN = {
        customer: [{ href: 'Customer_Profile_Page.html', label: 'My Profile', icon: 'fa-user' }],
        admin: []
    };

    function renderNav(role, activeKey) {
        const host = document.getElementById('lv-nav');
        if (!host) return;
        const s = session() || { name: 'Guest', email: '' };
        const links = (NAV[role] || []).map(function (l) {
            return '<a class="lv-navlink' + (l.key === activeKey ? ' active' : '') + '" href="' + l.href + '">' + l.label + '</a>';
        }).join('');
        const extra = (DROPDOWN[role] || []).map(function (l) {
            return '<a class="lv-dropdown-item" href="' + l.href + '"><i class="fa-solid ' + l.icon + '"></i>' + l.label + '</a>';
        }).join('');

        host.innerHTML =
            '<header class="lv-nav"><div class="lv-nav-inner">' +
                '<a class="lv-brand" href="' + (role === 'admin' ? 'Admin_Dashboard_Page.html' : 'Customer_Home_Page.html') + '">' +
                    '<img src="LAVANDERA.svg" alt="LaVanDera" onerror="this.onerror=null; this.src=\'Lavandera_logo.png\';">' +
                '</a>' +
                '<nav class="lv-navlinks">' + links + '</nav>' +
                '<div class="lv-menu-wrap">' +
                    '<button class="lv-menu-btn" id="lvMenuBtn" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>' +
                    '<div class="lv-dropdown" id="lvDropdown">' +
                        '<div class="lv-dropdown-head">' +
                            '<div class="lv-avatar">' + esc(initials(s.name)) + '</div>' +
                            '<div><div class="lv-dropdown-name">' + esc(s.name) + '</div>' +
                            '<div class="lv-dropdown-mail">' + esc(s.email) + '</div></div>' +
                        '</div>' +
                        extra +
                        '<button class="lv-dropdown-item danger" onclick="LV.logout()"><i class="fa-solid fa-right-from-bracket"></i>Logout</button>' +
                    '</div>' +
                '</div>' +
            '</div></header>';

        const btn = document.getElementById('lvMenuBtn'), dd = document.getElementById('lvDropdown');
        btn.addEventListener('click', function (e) { e.stopPropagation(); dd.classList.toggle('open'); });
        document.addEventListener('click', function (e) { if (!dd.contains(e.target)) dd.classList.remove('open'); });
    }

    /* ----------------------------------------------------- toast ---- */
    let toastTimer = null;
    function toast(msg, icon) {
        let el = document.getElementById('lvToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'lvToast'; el.className = 'lv-toast';
            el.innerHTML = '<i class="fa-solid fa-circle-check"></i><span id="lvToastMsg"></span>';
            document.body.appendChild(el);
        }
        el.querySelector('i').className = 'fa-solid ' + (icon || 'fa-circle-check');
        document.getElementById('lvToastMsg').textContent = msg;
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.classList.remove('show'); }, 3200);
    }

    /* ---------------------------------------------- dashboard KPIs -- */
    function adminStats() {
        const cs = customers(), rs = riders(), ss = stores(), os = orders();
        const done = os.filter(function (o) { return o.status === 'completed'; });
        return {
            totalCustomers: cs.length,
            activeRiders: rs.filter(function (r) { return r.status === 'active'; }).length,
            pendingRiders: rs.filter(function (r) { return r.status === 'pending_approval'; }).length,
            activeStores: ss.filter(function (s) { return s.status === 'active'; }).length,
            pendingStores: ss.filter(function (s) { return s.status === 'pending_approval'; }).length,
            totalOrders: os.length,
            inProgress: os.filter(function (o) { return ['pending_store', 'waiting_for_driver', 'enroute_to_store', 'at_laundromat', 'waiting_for_return_driver', 'enroute_to_customer'].indexOf(o.status) > -1; }).length,
            completedOrders: done.length,
            revenue: done.reduce(function (t, o) { return t + Number(o.total); }, 0)
        };
    }

    function statusBreakdown() {
        const map = {};
        orders().forEach(function (o) { map[o.status] = (map[o.status] || 0) + 1; });
        return FLOW.concat(['rejected', 'cancelled']).map(function (k) {
            return { status: k, label: (STATUS[k] || {}).label || k, count: map[k] || 0, tone: (STATUS[k] || {}).tone || 'gray' };
        });
    }

    /* ------------------------------------------------------ API ----- */
    return {
        settings: SETTINGS, bags: BAG_TYPES, IMG: IMG, STATUS: STATUS, FLOW: FLOW, USER_STATUS: USER_STATUS,
        peso: peso, bagOf: bagOf, deliveryFee: deliveryFee, distanceKm: distanceKm,
        statusBadge: statusBadge, userStatusBadge: userStatusBadge, initials: initials, esc: esc, when: when,
        customers: customers, riders: riders, stores: stores, orders: orders,
        customer: customer, store: store, order: order,
        publicStores: publicStores, ordersOfCustomer: ordersOfCustomer,
        addOrder: addOrder, setOrderStatus: setOrderStatus, setUserStatus: setUserStatus, updateCustomer: updateCustomer,
        login: login, signupCustomer: signupCustomer, session: session, logout: logout, requireRole: requireRole,
        renderNav: renderNav, toast: toast, adminStats: adminStats, statusBreakdown: statusBreakdown
    };
})();
