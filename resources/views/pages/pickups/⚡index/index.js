//<![CDATA[
(function() {
    let pickupFilterRetryCount = 0;

    const pickupFilterRoot = () => document.getElementById('pickup-index-filter-panel');

    const findLivewireComponent = (element) => {
        const componentEl = element?.closest('[wire\\:id]');
        const componentId = componentEl?.getAttribute('wire:id');

        return componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
    };

    const setLivewireModel = (input, value) => {
        const model = input?.dataset.livewireModel;
        const component = findLivewireComponent(input);
        const normalizedValue = value || '';

        if (model && component) {
            if (input.dataset.lastLivewireValue === normalizedValue) return;
            input.dataset.lastLivewireValue = normalizedValue;
            component.set(model, normalizedValue || null, true);
        }
    };

    const initPickupFilterControls = () => {
        const root = pickupFilterRoot();
        if (!root) return;

        if (!window.flatpickr || !window.TomSelectHelper) {
            if (pickupFilterRetryCount < 20) {
                pickupFilterRetryCount++;
                setTimeout(initPickupFilterControls, 100);
            }
            return;
        }

        root.querySelectorAll('input[data-pickup-date-picker]').forEach((input) => {
            if (input._flatpickr) return;

            window.flatpickr(input, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                allowInput: true,
                defaultDate: input.value || null,
                static: true,
                position: 'below left',
                positionElement: input,
                disableMobile: true,
                clickOpens: true,
                onReady: (_selectedDates, _dateStr, instance) => {
                    instance.calendarContainer.classList.add('pickup-filter-calendar');
                },
                onChange: (_selectedDates, dateStr) => {
                    setLivewireModel(input, dateStr);
                },
                onClose: (_selectedDates, dateStr) => {
                    setLivewireModel(input, dateStr);
                },
            });
        });

        window.TomSelectHelper.init(root);
    };

    const syncPickupFilterControls = (filters = {}) => {
        const root = pickupFilterRoot();
        if (!root) return;

        root.querySelectorAll('input[data-pickup-date-picker]').forEach((input) => {
            const model = input.dataset.livewireModel;
            const value = filters[model] || '';

            if (input._flatpickr) {
                input._flatpickr.setDate(value || null, false);
            } else {
                input.value = value;
            }

            input.dataset.lastLivewireValue = value;
        });

        root.querySelectorAll('select.pickup-filter-tomselect').forEach((select) => {
            const model = select.dataset.livewireModel || select.dataset.pickupFilterKey;
            const value = filters[model] || '';

            if (select.tomselect) {
                select.tomselect.setValue(value, true);
            } else {
                select.value = value;
            }
        });
    };

    const initPickupEditControls = () => {
        const form = document.getElementById('pickup-edit-form');
        if (!form || !window.TomSelectHelper) return;

        window.TomSelectHelper.init(form);
    };

    setTimeout(initPickupFilterControls, 75);

    document.addEventListener('pickup-filter-synced', (event) => {
        setTimeout(() => syncPickupFilterControls(event.detail?.filters || {}), 50);
    });

    document.addEventListener('pickup-edit-modal-opened', () => {
        setTimeout(initPickupEditControls, 75);
        setTimeout(initPickupEditControls, 200);
    });

    document.addEventListener('livewire:navigated', () => {
        setTimeout(initPickupFilterControls, 75);
        setTimeout(initPickupEditControls, 75);
    });

    new MutationObserver(() => {
        if (document.getElementById('pickup-index-filter-panel')) {
            setTimeout(initPickupFilterControls, 50);
        }

        if (document.getElementById('pickup-edit-form')) {
            setTimeout(initPickupEditControls, 50);
        }
    }).observe(document.body, { childList: true, subtree: true });
})();

(function() {
    const VIETMAP_GL_VERSION = '6.0.1';
    const VIETMAP_GL_CSS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.css`;
    const VIETMAP_GL_JS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.js`;
    const DEFAULT_CENTER = [106.66817068179284, 10.803866192772915];
    const ROUTE_SOURCE_ID = 'pickup-detail-route';
    const ROUTE_LAYER_ID = 'pickup-detail-route-line';
    const VIETMAP_PROXY_BASE = '/api/vietmap';

    let vietmapPromise = null;
    let detailMap = null;
    let detailMarker = null;
    let shipperMarker = null;
    let directionButtonBound = false;
    let editMap = null;
    let editMarker = null;
    let editGeocodeButtonBound = false;
    let editMapDragActive = false;

    function getTileApiKey() {
        return window.__VIETMAP_PUBLIC_CONFIG__?.tileApiKey || '';
    }

    function ensureVietmap() {
        if (window.vietmapgl) return Promise.resolve(window.vietmapgl);
        if (vietmapPromise) return vietmapPromise;

        if (!document.querySelector(`link[href="${VIETMAP_GL_CSS_URL}"]`)) {
            const stylesheet = document.createElement('link');
            stylesheet.rel = 'stylesheet';
            stylesheet.href = VIETMAP_GL_CSS_URL;
            stylesheet.crossOrigin = '';
            document.head.appendChild(stylesheet);
        }

        vietmapPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector(`script[src="${VIETMAP_GL_JS_URL}"]`);
            const script = existingScript || document.createElement('script');

            script.addEventListener('load', () => {
                window.vietmapgl ? resolve(window.vietmapgl) : reject(new Error('VietMap GL missing.'));
            }, { once: true });
            script.addEventListener('error', reject, { once: true });

            if (!existingScript) {
                script.src = VIETMAP_GL_JS_URL;
                script.crossOrigin = '';
                document.head.appendChild(script);
            } else if (window.vietmapgl) {
                resolve(window.vietmapgl);
            }
        }).catch((error) => {
            vietmapPromise = null;
            throw error;
        });

        return vietmapPromise;
    }

    function getVietmapStyleUrl() {
        return `https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=${encodeURIComponent(getTileApiKey())}`;
    }

    function getVietmapUrl(path, params = {}) {
        const url = new URL(`${VIETMAP_PROXY_BASE}/${path}`, window.location.origin);

        Object.entries(params).forEach(([key, value]) => {
        if (Array.isArray(value)) {
                value.forEach((item) => url.searchParams.append(`${key}[]`, item));
                return;
            }

            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.set(key, value);
            }
        });

        return url.toString();
    }

    async function fetchVietmapJson(path, params = {}) {
        const response = await fetch(getVietmapUrl(path, params), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            const message = response.status === 503 ? 'missing-config' : 'vietmap-request-failed';
            throw new Error(message);
        }

        return response.json();
    }

    function setStatus(text) {
        const statusEl = document.getElementById('pickup-detail-map-status');
        if (statusEl && text) statusEl.textContent = text;
    }

    function waitForDetailMapReady() {
        if (!detailMap || detailMap.loaded?.()) return Promise.resolve();

        return new Promise((resolve) => {
            let resolved = false;
            const done = () => {
                if (resolved) return;
                resolved = true;
                resolve();
            };

            detailMap.once?.('load', done);
            detailMap.once?.('styledata', done);
            setTimeout(done, 1200);
        });
    }

    function markerElement(color, label) {
        const element = document.createElement('div');
        element.style.cssText = `
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #fff;
            border-radius: 999px;
            background: ${color};
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(15,23,42,.28);
        `;
        element.textContent = label;
        return element;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function findLivewireComponent(element) {
        const componentId = element?.closest('[wire\\:id]')?.getAttribute('wire:id');

        return componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
    }

    function updateEditMapStatus(text) {
        const status = document.getElementById('pickup-edit-map-status');

        if (status && text) status.textContent = text;
    }

    function editMarkerElement() {
        const element = document.createElement('div');
        element.style.cssText = `
            position: relative;
            width: 44px;
            height: 54px;
            cursor: grab;
            transform: translateY(-4px);
            filter: drop-shadow(0 14px 18px rgba(15, 23, 42, 0.28));
        `;
        element.innerHTML = `
            <span style="
                position:absolute;
                left:50%;
                bottom:2px;
                width:30px;
                height:10px;
                border-radius:999px;
                background:rgba(15,23,42,.18);
                transform:translateX(-50%);
                filter:blur(3px);
            "></span>
            <span style="
                position:absolute;
                left:50%;
                top:2px;
                display:flex;
                align-items:center;
                justify-content:center;
                width:42px;
                height:42px;
                border:3px solid #fff;
                border-radius:50% 50% 50% 12px;
                background:linear-gradient(135deg,#ef4444,#dc2626 52%,#991b1b);
                color:#fff;
                transform:translateX(-50%) rotate(-45deg);
                box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 10px 24px rgba(220,38,38,.36);
            ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="transform:rotate(45deg);">
                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
            </span>
        `;

        return element;
    }

    function syncEditCoordinates(lat, lng) {
        const form = document.getElementById('pickup-edit-form');
        const component = findLivewireComponent(form);

        if (!component) return;

        component.set('editForm.pickup_lat', lat.toFixed(7), false);
        component.set('editForm.pickup_lng', lng.toFixed(7), false);
    }

    function placeEditMarker(lat, lng) {
        if (!editMap) return;

        if (editMarker) {
            editMarker.setLngLat([lng, lat]);
        } else {
            const element = editMarkerElement();
            editMarker = new window.vietmapgl.Marker({ element, draggable: true, anchor: 'bottom' })
                .setLngLat([lng, lat])
                .addTo(editMap);

            element.addEventListener('mousedown', () => {
                element.style.cursor = 'grabbing';
            });

            editMarker.on('dragstart', () => {
                editMapDragActive = true;
                updateEditMapStatus('Đang di chuyển marker...');
            });

            editMarker.on('dragend', () => {
                const lngLat = editMarker.getLngLat();
                editMapDragActive = false;
                syncEditCoordinates(lngLat.lat, lngLat.lng);
                updateEditMapStatus('Đã di chuyển marker');
            });
        }

        editMap.flyTo({ center: [lng, lat], zoom: 16, duration: 500 });
        syncEditCoordinates(lat, lng);
    }

    function addEditCenterPinButton() {
        const mapElement = document.getElementById('pickup-edit-map');
        if (!mapElement || document.getElementById('pickup-edit-center-pin-btn')) return;

        mapElement.style.position = 'relative';

        const button = document.createElement('button');
        button.id = 'pickup-edit-center-pin-btn';
        button.type = 'button';
        button.setAttribute('aria-label', 'Ghim tâm bản đồ');
        button.title = 'Ghim tâm bản đồ';
        button.innerHTML = `
            <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:linear-gradient(135deg,#0f766e,#14b8a6);color:#fff;box-shadow:inset 0 1px 0 rgba(255,255,255,.28),0 8px 18px rgba(20,184,166,.28);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
            </span>
            <span style="white-space:nowrap;">Ghim tâm</span>
        `;
        button.style.cssText = `
            position: absolute;
            left: 10px;
            top: 10px;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            border: 1px solid rgba(15, 118, 110, 0.18);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.92);
            color: #0f766e;
            cursor: pointer;
            font-size: 13px;
            font-weight: 800;
            line-height: 1;
            padding: 6px 13px 6px 6px;
            backdrop-filter: blur(10px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18), 0 1px 2px rgba(15, 23, 42, 0.12);
        `;

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (!editMap || editMapDragActive) return;

            const center = editMap.getCenter();
            placeEditMarker(center.lat, center.lng);
            updateEditMapStatus('Đã ghim vị trí trung tâm bản đồ. Kéo marker nếu cần điều chỉnh vị trí chính xác hơn.');
        });

        mapElement.appendChild(button);
    }

    async function initEditMap() {
        const mapEl = document.getElementById('pickup-edit-map');
        if (!mapEl || editMap) return;
        if (mapEl.offsetParent === null) return;

        if (!getTileApiKey()) {
            updateEditMapStatus('Chưa cấu hình VietMap Tile API Key.');
            return;
        }

        try {
            await ensureVietmap();
        } catch {
            updateEditMapStatus('Không tải được bản đồ VietMap.');
            return;
        }

        const lat = parseFloat(mapEl.dataset.pickupLat);
        const lng = parseFloat(mapEl.dataset.pickupLng);
        const hasCoords = Number.isFinite(lat) && Number.isFinite(lng) && lat !== 0 && lng !== 0;

        editMap = new window.vietmapgl.Map({
            container: mapEl,
            style: getVietmapStyleUrl(),
            center: hasCoords ? [lng, lat] : DEFAULT_CENTER,
            zoom: hasCoords ? 16 : 9,
        });
        editMap.addControl(new window.vietmapgl.NavigationControl(), 'top-right');
        editMap.on('error', () => updateEditMapStatus('Không tải được tile VietMap. Vui lòng kiểm tra Tile API Key.'));
        editMap.on('load', () => {
            addEditCenterPinButton();
            requestAnimationFrame(() => editMap?.resize());
            setTimeout(() => editMap?.resize(), 200);

            if (hasCoords) {
                placeEditMarker(lat, lng);
                updateEditMapStatus(`Tọa độ: ${lat}, ${lng}`);
            }
        });

        const button = document.getElementById('pickup-edit-geocode-btn');
        if (button && !editGeocodeButtonBound) {
            editGeocodeButtonBound = true;
            button.addEventListener('click', (event) => {
                event.preventDefault();
                geocodeEditAddress();
            });
        }
    }

    async function geocodeEditAddress() {
        const form = document.getElementById('pickup-edit-form');
        const component = findLivewireComponent(form);
        const editForm = component?.get('editForm') || {};
        const address = editForm.address || '';
        const citySelect = form?.querySelector('select[data-livewire-model="editForm.id_city"]');
        const wardSelect = form?.querySelector('select[data-livewire-model="editForm.id_ward"]');
        const cityName = citySelect?.selectedIndex > 0 ? citySelect.options[citySelect.selectedIndex].text : '';
        const wardName = wardSelect?.selectedIndex > 0 ? wardSelect.options[wardSelect.selectedIndex].text : '';
        const fullAddress = [address, wardName, cityName, 'Vietnam'].filter(Boolean).join(', ');

        if (!address && !cityName) {
            updateEditMapStatus('Chưa có địa chỉ để tìm kiếm. Kéo bản đồ rồi bấm Ghim tâm để chọn.');
            return;
        }

        updateEditMapStatus('Đang tìm kiếm vị trí...');

        try {
            const results = normalizeGeocodeResults(await fetchVietmapJson('search', {
                text: fullAddress,
                focus: '10.803866192772915,106.66817068179284',
                display_type: 5,
            }));
            const first = results[0];
            const place = await fetchPlaceByRefId(getGeocodeRefId(first));
            const coordinate = getGeocodeCoordinate(place) || getGeocodeCoordinate(first);

            if (!coordinate) {
                updateEditMapStatus('Không lấy được tọa độ từ VietMap Place. Kéo bản đồ rồi bấm Ghim tâm để chọn.');
                return;
            }

            placeEditMarker(coordinate.lat, coordinate.lng);
            updateEditMapStatus('Đã tìm thấy vị trí. Kéo marker nếu cần điều chỉnh.');
        } catch (error) {
            updateEditMapStatus(error.message === 'missing-config'
                ? 'Chưa cấu hình VietMap Geocode API Key.'
                : 'Lỗi khi tìm vị trí. Kéo bản đồ rồi bấm Ghim tâm để chọn.');
        }
    }

    async function initDetailMap() {
        const mapEl = document.getElementById('pickup-detail-map');
        if (!mapEl || detailMap) return;
        if (mapEl.offsetParent === null) return;

        const lat = parseFloat(mapEl.dataset.pickupLat);
        const lng = parseFloat(mapEl.dataset.pickupLng);
        const hasCoords = !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0;

        if (!getTileApiKey()) {
            setStatus('Chưa cấu hình VietMap Tile API Key.');
            return;
        }

        try {
            await ensureVietmap();
        } catch {
            setStatus('Không tải được bản đồ VietMap.');
            return;
        }

        const center = hasCoords ? [lng, lat] : DEFAULT_CENTER;
        const zoom = hasCoords ? 16 : 12;

        detailMap = new window.vietmapgl.Map({
            container: mapEl,
            style: getVietmapStyleUrl(),
            center,
            zoom,
        });
        detailMap.addControl(new window.vietmapgl.NavigationControl(), 'top-right');
        detailMap.on('error', () => setStatus('Không tải được tile VietMap. Vui lòng kiểm tra Tile API Key.'));
        detailMap.on('load', () => {
            requestAnimationFrame(() => detailMap?.resize());
            setTimeout(() => detailMap?.resize(), 200);
        });

        if (hasCoords) {
            placePickupMarker(lat, lng, mapEl.dataset.pickupAddress || '');
        } else {
            const address = mapEl.dataset.pickupAddress;
            if (address) {
                geocodeForDetail(address);
            }
        }

        // Direction button
        const dirBtn = document.getElementById('pickup-direction-btn');
        if (dirBtn && !directionButtonBound) {
            directionButtonBound = true;
            dirBtn.addEventListener('click', function(e) {
                e.preventDefault();
                getDirections();
            });
        }
    }

    function normalizeGeocodeResults(payload) {
        if (Array.isArray(payload)) return payload;
        if (Array.isArray(payload?.data)) return payload.data;
        if (Array.isArray(payload?.results)) return payload.results;
        return payload ? [payload] : [];
    }

    function getGeocodeCoordinate(result) {
        const lat = parseFloat(result?.lat ?? result?.location?.lat);
        const lng = parseFloat(result?.lng ?? result?.lon ?? result?.location?.lng ?? result?.location?.lon);

        if (Number.isNaN(lat) || Number.isNaN(lng)) return null;

        return { lat, lng };
    }

    function getGeocodeRefId(result) {
        return result?.ref_id || result?.refid || result?.data_new?.ref_id || result?.data_old?.ref_id || null;
    }

    async function fetchPlaceByRefId(refId) {
        if (!refId) return null;

        try {
            return await fetchVietmapJson('place', { refid: refId });
        } catch {
            return null;
        }
    }

    async function geocodeForDetail(address) {
        try {
            const results = normalizeGeocodeResults(await fetchVietmapJson('search', {
                text: `${address}, Vietnam`,
                display_type: 5,
            }));
            const first = results[0];
            const place = await fetchPlaceByRefId(getGeocodeRefId(first));
            const coordinate = getGeocodeCoordinate(place) || getGeocodeCoordinate(first);

            if (!coordinate) {
                setStatus('Không lấy được tọa độ từ địa chỉ.');
                return;
            }

            placePickupMarker(coordinate.lat, coordinate.lng, address);
            detailMap?.flyTo({ center: [coordinate.lng, coordinate.lat], zoom: 16, duration: 500 });

            const btn = document.getElementById('pickup-direction-btn');
            if (btn) {
                btn.dataset.lat = coordinate.lat;
                btn.dataset.lng = coordinate.lng;
            }
            setStatus('Vị trí ước lượng từ địa chỉ bằng VietMap.');
        } catch (error) {
            setStatus(error.message === 'missing-config'
                ? 'Chưa cấu hình VietMap Geocode API Key.'
                : 'Không tìm được vị trí trên VietMap từ địa chỉ.');
        }
    }

    function placePickupMarker(lat, lng, address) {
        if (!detailMap) return;

        detailMarker?.remove();
        detailMarker = new window.vietmapgl.Marker({ element: markerElement('#dc2626', 'P') })
            .setLngLat([lng, lat])
            .setPopup(
                new window.vietmapgl.Popup({ offset: 24 })
                    .setHTML(`<strong>Điểm lấy hàng</strong><br>${escapeHtml(address)}`)
            )
            .addTo(detailMap);
    }

    function getDirections() {
        const btn = document.getElementById('pickup-direction-btn');
        const destLat = parseFloat(btn?.dataset.lat);
        const destLng = parseFloat(btn?.dataset.lng);

        if (isNaN(destLat) || isNaN(destLng) || destLat === 0) {
            setStatus('Chưa có tọa độ điểm lấy hàng.');
            return;
        }

        setStatus('Đang lấy vị trí của bạn...');

        if (!navigator.geolocation) {
            setStatus('Trình duyệt không hỗ trợ GPS.');
            openGoogleMapsNavigation(destLat, destLng);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const shipperLat = pos.coords.latitude;
                const shipperLng = pos.coords.longitude;
                setStatus('Đang tìm đường bằng VietMap...');
                fetchRoute(shipperLat, shipperLng, destLat, destLng);
            },
            function() {
                setStatus('Không lấy được vị trí. Mở Google Maps...');
                openGoogleMapsNavigation(destLat, destLng);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    async function fetchRoute(fromLat, fromLng, toLat, toLng) {
        shipperMarker?.remove();
        if (detailMap) {
            shipperMarker = new window.vietmapgl.Marker({ element: markerElement('#2563eb', 'S') })
                .setLngLat([fromLng, fromLat])
                .addTo(detailMap);
        }

        try {
            const data = await fetchVietmapJson('route', {
                points_encoded: 'false',
                vehicle: 'motorcycle',
                point: [
                    `${fromLat},${fromLng}`,
                    `${toLat},${toLng}`,
                ],
            });
            if (data?.code !== 'OK' || !Array.isArray(data?.paths) || !data.paths[0]) {
                throw new Error(data?.messages || 'Không tìm thấy tuyến đường.');
            }

            const path = data.paths[0];
            await waitForDetailMapReady();
            drawRoute({ lat: fromLat, lng: fromLng }, { lat: toLat, lng: toLng }, path);

            const distance = formatDistance(path.distance);
            const duration = formatDuration(path.time);
            setStatus(`Khoảng cách: ${distance} - Thời gian: ~${duration}`);

            const routeInfo = document.getElementById('pickup-route-info');
            const routeText = document.getElementById('pickup-route-text');
            if (routeInfo && routeText) {
                routeText.textContent = `${distance} - ~${duration} lái xe`;
                routeInfo.classList.remove('hidden');
            }
        } catch (error) {
            setStatus(error.message === 'missing-config'
                ? 'Chưa cấu hình VietMap Geocode/Route API Key. Mở Google Maps...'
                : 'Lỗi tìm đường bằng VietMap. Mở Google Maps...');
            openGoogleMapsNavigation(toLat, toLng);
        }
    }

    function formatDistance(meters) {
        const value = Number(meters || 0);
        if (!value) return '-';
        return value >= 1000 ? `${(value / 1000).toFixed(1)} km` : `${Math.round(value)} m`;
    }

    function formatDuration(ms) {
        const minutes = Math.max(1, Math.round(Number(ms || 0) / 60000));
        if (minutes < 60) return `${minutes} phút`;
        return `${Math.floor(minutes / 60)} giờ ${minutes % 60} phút`;
    }

    function routeCoordinates(path) {
        const points = path?.points?.coordinates || path?.points;
        if (!Array.isArray(points)) return [];

        return points
            .map((point) => {
                if (!Array.isArray(point) || point.length < 2) return null;
                const a = Number(point[0]);
                const b = Number(point[1]);
                if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
                return Math.abs(a) <= 90 && Math.abs(b) <= 180 ? [b, a] : [a, b];
            })
            .filter(Boolean);
    }

    function drawRoute(origin, destination, path) {
        if (!detailMap) return;

        if (detailMap.getLayer(ROUTE_LAYER_ID)) detailMap.removeLayer(ROUTE_LAYER_ID);
        if (detailMap.getSource(ROUTE_SOURCE_ID)) detailMap.removeSource(ROUTE_SOURCE_ID);

        const coordinates = routeCoordinates(path);

        if (coordinates.length) {
            detailMap.addSource(ROUTE_SOURCE_ID, {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    geometry: { type: 'LineString', coordinates },
                    properties: {},
                },
            });
            detailMap.addLayer({
                id: ROUTE_LAYER_ID,
                type: 'line',
                source: ROUTE_SOURCE_ID,
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: { 'line-color': '#0f766e', 'line-width': 5, 'line-opacity': 0.88 },
            });
        }

        const bounds = new window.vietmapgl.LngLatBounds([origin.lng, origin.lat], [origin.lng, origin.lat]);
        bounds.extend([destination.lng, destination.lat]);
        coordinates.forEach((coordinate) => bounds.extend(coordinate));
        detailMap.fitBounds(bounds, { padding: 72, maxZoom: 16, duration: 600 });
    }

    function openGoogleMapsNavigation(lat, lng) {
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`, '_blank');
    }

    document.addEventListener('pickup-edit-modal-opened', () => {
        setTimeout(initEditMap, 100);
        setTimeout(() => editMap?.resize(), 300);
    });

    // Observe modal visibility
    const observer = new MutationObserver(() => {
        const mapEl = document.getElementById('pickup-detail-map');
        if (mapEl && mapEl.offsetParent !== null && !detailMap) {
            setTimeout(initDetailMap, 250);
        }
        // Cleanup when modal closes
        if (detailMap && (!mapEl || mapEl.offsetParent === null)) {
            detailMap.remove();
            detailMap = null;
            detailMarker = null;
            shipperMarker = null;
            directionButtonBound = false;
            document.getElementById('pickup-route-info')?.classList.add('hidden');
        }

        const editMapEl = document.getElementById('pickup-edit-map');
        if (editMapEl && editMapEl.offsetParent !== null && !editMap) {
            setTimeout(initEditMap, 120);
        }

        if (editMap && (!editMapEl || editMapEl.offsetParent === null)) {
            editMap.remove();
            editMap = null;
            editMarker = null;
            editGeocodeButtonBound = false;
            editMapDragActive = false;
            document.getElementById('pickup-edit-center-pin-btn')?.remove();
        }
    });

    observer.observe(document.body, { attributes: true, subtree: true, childList: true });
    document.addEventListener('livewire:navigated', () => {
        detailMap = null;
        detailMarker = null;
        shipperMarker = null;
        directionButtonBound = false;
        editMap = null;
        editMarker = null;
        editGeocodeButtonBound = false;
        editMapDragActive = false;
    });
})();
