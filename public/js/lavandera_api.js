/**
 * LaVanDera API client: talks to the PHP endpoints in public/api/.
 * Load AFTER lavandera_demo.js (it reuses the UI helpers there: LV.esc, LV.peso, LV.renderNav ...)
 * and AFTER the page defines window.LV_CONFIG = { loginUrl, logoutUrl, csrf } and window.LV_SESSION.
 * Load it BEFORE any page code that calls LV.renderNav().
 */
(function () {
    var cfg = window.LV_CONFIG || {};

    async function request(url, options) {
        var res;
        try {
            res = await fetch(url, Object.assign({ credentials: 'same-origin' }, options));
        } catch (networkError) {
            throw new Error('Cannot reach the server. Check that Apache is running.');
        }
        var data = null;
        try { data = await res.json(); } catch (parseError) { /* not JSON */ }
        if (res.status === 401) {
            location.href = cfg.loginUrl || '../auth/Log_In_Page.html';
            throw new Error('Please sign in.');
        }
        if (!res.ok || !data || data.ok === false) {
            throw new Error((data && data.error) || 'Request failed (' + res.status + ').');
        }
        return data;
    }

    window.LVApi = {
        get: function (url) { return request(url); },
        postJson: function (url, body) {
            return request(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf || '' },
                body: JSON.stringify(body)
            });
        },
        postForm: function (url, formData) {
            return request(url, { method: 'POST', headers: { 'X-CSRF-Token': cfg.csrf || '' }, body: formData });
        },
        logout: function () { location.href = cfg.logoutUrl || '../auth/logout.php'; }
    };

    // 1) The shared navbar in lavandera_demo.js reads its user from localStorage['lv_session'].
    //    Mirror the real PHP session there so the navbar shows the signed-in store.
    try {
        if (window.LV_SESSION) localStorage.setItem('lv_session', JSON.stringify(window.LV_SESSION));
        else localStorage.removeItem('lv_session');
    } catch (storageError) { /* storage blocked: navbar falls back to its default */ }

    // 2) The demo's sign-out confirm dialog clears localStorage and goes to the login page, which would
    //    leave the PHP session alive. Intercept its confirm button (capture phase) and end the real session.
    document.addEventListener('click', function (event) {
        var btn = event.target && event.target.closest ? event.target.closest('.lv-logout-confirm') : null;
        if (!btn) return;
        event.stopImmediatePropagation();
        event.preventDefault();
        try { localStorage.removeItem('lv_session'); } catch (e) { /* ignore */ }
        window.LVApi.logout();
    }, true);

    // 3) Until the shared navbar links to .php, point its store links at the PHP pages.
    window.LVApi.fixNavLinks = function () {
        document.querySelectorAll('#lv-nav a[href]').forEach(function (a) {
            a.setAttribute('href', a.getAttribute('href').replace(/(Store_(?:Dashboard|Orders|Setup)_Page)\.html/, '$1.php'));
        });
    };
})();
