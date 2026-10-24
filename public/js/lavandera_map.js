/**
 * LaVanDera map picker (OpenStreetMap + Leaflet + Nominatim geocoding).
 * Needs Leaflet (window.L) loaded first. Reusable for the customer address too:
 *
 *   LVMap.picker({
 *     mapEl:   document.getElementById('map'),
 *     address: document.getElementById('address'),      // text input to geocode
 *     findBtn: document.getElementById('findOnMap'),    // "Find on map" button
 *     lat:     document.getElementById('latitude'),     // hidden inputs that get submitted
 *     lng:     document.getElementById('longitude'),
 *     status:  document.getElementById('mapStatus'),    // optional message element
 *     initial: { lat: 14.5995, lng: 120.9842 }          // optional saved pin
 *   });
 *
 * Geocoding only runs when the button is pressed (Nominatim usage policy: no autocomplete,
 * max 1 request/second). The pin can also be dragged, or the map clicked, to fine-tune it.
 */
(function () {
    var DEFAULT_CENTER = [14.5995, 120.9842];            // Metro Manila
    var NOMINATIM = 'https://nominatim.openstreetmap.org/search';

    function valid(lat, lng) {
        return isFinite(lat) && isFinite(lng) && lat >= 4 && lat <= 22 && lng >= 115 && lng <= 128;
    }

    function picker(opts) {
        var status = opts.status;
        function say(text, isError) {
            if (!status) return;
            status.textContent = text || '';
            status.style.color = isError ? '#b3261e' : '';
        }

        if (typeof L === 'undefined') {
            say('The map could not load. Check your internet connection and reload the page.', true);
            return null;
        }

        var start = opts.initial && valid(+opts.initial.lat, +opts.initial.lng)
            ? [+opts.initial.lat, +opts.initial.lng] : null;
        var map = L.map(opts.mapEl).setView(start || DEFAULT_CENTER, start ? 17 : 12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var marker = null;
        function setPin(lat, lng, zoom) {
            lat = +lat; lng = +lng;
            if (marker) marker.setLatLng([lat, lng]);
            else {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', function () { var p = marker.getLatLng(); store(p.lat, p.lng); });
            }
            store(lat, lng);
            if (zoom) map.setView([lat, lng], zoom);
        }
        function store(lat, lng) {
            opts.lat.value = (+lat).toFixed(7);
            opts.lng.value = (+lng).toFixed(7);
            say('Pin set. Drag the pin or click the map to adjust it.');
        }
        function clear() { opts.lat.value = ''; opts.lng.value = ''; }

        if (start) setPin(start[0], start[1]);
        else clear();

        map.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng); });

        function find() {
            var q = (opts.address.value || '').trim();
            if (!q) { say('Type the address first, then press "Find on map".', true); return; }
            if (opts.findBtn) opts.findBtn.disabled = true;
            say('Searching...');
            var url = NOMINATIM + '?format=jsonv2&limit=1&countrycodes=ph&q=' + encodeURIComponent(q);
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('bad status'); return r.json(); })
                .then(function (rows) {
                    if (!rows || !rows.length) {
                        say('Address not found. Try adding the barangay and city, or click the map to place the pin yourself.', true);
                        return;
                    }
                    var lat = parseFloat(rows[0].lat), lng = parseFloat(rows[0].lon);
                    if (!valid(lat, lng)) { say('That result is outside the Philippines. Place the pin manually.', true); return; }
                    setPin(lat, lng, 17);
                })
                .catch(function () { say('Address search is unavailable right now. Click the map to place the pin yourself.', true); })
                .then(function () { if (opts.findBtn) opts.findBtn.disabled = false; });
        }
        if (opts.findBtn) opts.findBtn.addEventListener('click', find);
        // Pressing Enter in the address box searches instead of submitting the form.
        opts.address.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); find(); }
        });

        return { map: map, setPin: setPin, find: find };
    }

    window.LVMap = { picker: picker, valid: valid };
})();
