const VIETMAP_GL_VERSION = '6.0.1';
const VIETMAP_GL_CSS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.css`;
const VIETMAP_GL_JS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.js`;
const MAP_ELEMENT_ID = 'pickup-create-map';
const CENTER_PIN_BUTTON_ID = 'pickup-center-pin-btn';
const DEFAULT_FOCUS = '10.7769,106.7009';

let vietmapPromise = null;
let map = null;
let marker = null;
let observer = null;
let setupTimeout = null;
let geocodeTimeout = null;
let mapDragActive = false;
let suppressNextClick = false;

function suppressClickAfterDrag() {
    suppressNextClick = true;
    setTimeout(() => {
        suppressNextClick = false;
    }, 250);
}

function getMapElement() {
    return document.getElementById(MAP_ELEMENT_ID);
}

function getCenterPinButton() {
    return document.getElementById(CENTER_PIN_BUTTON_ID);
}

function getPickupComponentRoot() {
    return getMapElement()?.closest('[wire\\:id]') ?? null;
}

function getPickupComponent() {
    const componentId = getPickupComponentRoot()?.getAttribute('wire:id');

    return componentId && window.Livewire?.find
        ? window.Livewire.find(componentId)
        : null;
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
        const script = existingScript ?? document.createElement('script');

        script.addEventListener('load', () => {
            if (window.vietmapgl) {
                resolve(window.vietmapgl);
                return;
            }

            reject(new Error('VietMap GL loaded but window.vietmapgl is missing.'));
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
        updateStatus('Khong tai duoc ban do VietMap. Vui long thu lai.');
        throw error;
    });

    return vietmapPromise;
}

function scheduleSetup(delay = 0) {
    clearTimeout(setupTimeout);
    setupTimeout = setTimeout(setupMap, delay);
}

function getTileApiKey() {
    return window.__VIETMAP_CONFIG__?.tileApiKey || import.meta.env.VITE_VIETMAP_API_KEY_TITLE;
}

function getGeocodeApiKey() {
    return window.__VIETMAP_CONFIG__?.geocodeApiKey || import.meta.env.VITE_VIETMAP_API_KEY;
}

function createVietmapRasterStyle(apiKey) {
    return {
        version: 8,
        sources: {
            'vietmap-raster': {
                type: 'raster',
                tiles: [
                    `https://maps.vietmap.vn/maps/tiles/st/{z}/{x}/{y}.png?apikey=${apiKey}`,
                ],
                tileSize: 256,
                attribution: '© VietMap',
            },
        },
        layers: [
            {
                id: 'vietmap-raster',
                type: 'raster',
                source: 'vietmap-raster',
            },
        ],
    };
}

function getVietmapStreetStyleUrl(apiKey) {
    return `https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=${apiKey}`;
}

function getVietmapTrafficStyleUrl(apiKey) {
    return `https://maps.vietmap.vn/maps/styles/tf/style.json?apikey=${apiKey}`;
}

async function addTrafficOverlay(apiKey) {
    if (!map) return;

    try {
        const response = await fetch(getVietmapTrafficStyleUrl(apiKey), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) return;

        const trafficStyle = await response.json();
        const sources = trafficStyle?.sources || {};
        const layers = Array.isArray(trafficStyle?.layers) ? trafficStyle.layers : [];

        Object.entries(sources).forEach(([sourceId, source]) => {
            if (!map.getSource(sourceId)) {
                map.addSource(sourceId, source);
            }
        });

        layers.forEach((layer) => {
            if (layer?.id && !map.getLayer(layer.id)) {
                map.addLayer(layer);
            }
        });
    } catch {
        updateStatus('Khong tai duoc lop giao thong VietMap.');
    }
}

async function setupMap() {
    const mapElement = getMapElement();

    if (!mapElement || map || mapElement.offsetParent === null) return;

    try {
        await ensureVietmap();
    } catch {
        return;
    }

    if (!document.body.contains(mapElement) || mapElement.offsetParent === null || map) return;

    const tileApiKey = getTileApiKey();

    if (!tileApiKey) {
        updateStatus('Chua cau hinh VITE_VIETMAP_API_KEY_TITLE');
        return;
    }

    map = new window.vietmapgl.Map({
        container: mapElement,
        style: getVietmapStreetStyleUrl(tileApiKey),
        center: [106.66817068179284, 10.803866192772915],
        zoom: 9,
    });
    map.addControl(new vietmapgl.NavigationControl());
    map.addControl(
        new vietmapgl.GeolocateControl({
            positionOptions: {
                enableHighAccuracy: true
            },
            trackUserLocation: true
        })
    );
    map.on('load', () => {
        updateStatus('Đã tải bản đồ. Kéo bản đồ đến vị trí lấy hàng, sau đó bấm Ghim tâm bản đồ nếu tự động tìm không chính xác.');
        bindGeocodeButton();
        bindCenterPinButton();
        map?.dragPan?.enable();
        map?.touchZoomRotate?.enable();
        setTimeout(() => map?.resize(), 300);
    });
    map.on('load', () => {
        addTrafficOverlay(tileApiKey);
    });

    map.on('error', ({ error }) => {
        const message = error?.message || '';

        if (
            message.includes('401') ||
            message.includes('403') ||
            message.includes('423') ||
            message.toLowerCase().includes('apikey') ||
            message.toLowerCase().includes('limited')
        ) {
            updateStatus('VietMap API key chua co quyen Tilemap hoac dang bi gioi han.');
            return;
        }

        updateStatus('Khong tai duoc tile ban do VietMap. Vui long kiem tra key va ket noi mang.');
    });

    map.on('dragstart', () => {
        mapDragActive = true;
    });

    map.on('dragend', () => {
        mapDragActive = false;
        suppressClickAfterDrag();
    });

    clearTimeout(geocodeTimeout);
   geocodeTimeout = setTimeout(geocodeAddress, 500);
}

function bindGeocodeButton() {
    const button = document.getElementById('pickup-geocode-btn');

    if (!button || button.dataset.pickupMapBound === 'true') return;

    button.dataset.pickupMapBound = 'true';
    button.addEventListener('click', (event) => {
        event.preventDefault();
        geocodeAddress();
    });
}

function bindCenterPinButton() {
    const mapElement = getMapElement();

    if (!mapElement || getCenterPinButton()) return;

    mapElement.style.position = 'relative';

    const button = document.createElement('button');
    button.id = CENTER_PIN_BUTTON_ID;
    button.type = 'button';
    button.setAttribute('aria-label', 'Ghim tâm bản đồ');
    button.title = 'Ghim tâm bản đồ';
    button.innerHTML = `
        <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;background:#0f766e;color:#fff;box-shadow:inset 0 1px 0 rgba(255,255,255,.28);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0Z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
        </span>
        <span style="white-space:nowrap;">Ghim tâm</span>
    `;
    button.textContent = 'Ghim tâm bản đồ';
    button.innerHTML = `
        <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:linear-gradient(135deg,#0f766e,#14b8a6);color:#fff;box-shadow:inset 0 1px 0 rgba(255,255,255,.28),0 8px 18px rgba(20,184,166,.28);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0Z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
        </span>
        <span style="white-space:nowrap;">Ghim tÃ¢m</span>
    `;
    button.setAttribute('aria-label', 'Ghim tâm bản đồ');
    button.title = 'Ghim tâm bản đồ';
    button.querySelector('span:last-child')?.replaceChildren(document.createTextNode('Ghim tâm'));
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
        letter-spacing: 0;
        line-height: 1;
        padding: 6px 13px 6px 6px;
        backdrop-filter: blur(10px);
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18), 0 1px 2px rgba(15, 23, 42, 0.12);
        transition: transform 140ms ease, box-shadow 140ms ease, background 140ms ease, border-color 140ms ease;
    `;

    button.addEventListener('mouseenter', () => {
        button.style.background = '#ffffff';
        button.style.borderColor = 'rgba(15, 118, 110, 0.32)';
        button.style.boxShadow = '0 16px 34px rgba(15, 23, 42, 0.22), 0 1px 2px rgba(15, 23, 42, 0.14)';
        button.style.transform = 'translateY(-1px)';
    });
    button.addEventListener('mouseleave', () => {
        button.style.background = 'rgba(255, 255, 255, 0.92)';
        button.style.borderColor = 'rgba(15, 118, 110, 0.18)';
        button.style.boxShadow = '0 12px 30px rgba(15, 23, 42, 0.18), 0 1px 2px rgba(15, 23, 42, 0.12)';
        button.style.transform = 'translateY(0)';
    });
    button.addEventListener('mousedown', () => {
        button.style.transform = 'translateY(0) scale(0.98)';
    });
    button.addEventListener('mouseup', () => {
        button.style.transform = 'translateY(-1px) scale(1)';
    });

    button.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (!map || mapDragActive || suppressNextClick) return;

        const center = map.getCenter();
        placeMarker(center.lat, center.lng);
        updateStatus('Đã ghim vị trí trung tâm bản đồ. Kéo marker nếu cần điều chỉnh vị trí chính xác hơn.');
    });

    mapElement.appendChild(button);
}

function placeMarker(lat, lng) {
    if (!map) return;

    const el = document.createElement('div');
    el.className = 'pickup-marker';
    el.style.cssText = `
        position: relative;
        width: 44px;
        height: 54px;
        cursor: grab;
        transform: translateY(-4px);
        filter: drop-shadow(0 14px 18px rgba(15, 23, 42, 0.28));
    `;
    el.innerHTML = `
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
    if (marker) {
        marker.setLngLat([lng, lat]);
    } else {
        marker = new window.vietmapgl.Marker({ element: el, draggable: true, anchor: 'bottom' })
            .setLngLat([lng, lat])
            .addTo(map);

        el.addEventListener('mousedown', () => {
            el.style.cursor = 'grabbing';
        });
        marker.on('dragstart', () => {
            mapDragActive = true;
            updateStatus('Đang di chuyển marker...');
        });

        marker.on('dragend', () => {
            const lngLat = marker.getLngLat();
            mapDragActive = false;
            suppressClickAfterDrag();
            syncCoordinates(lngLat.lat, lngLat.lng);
            updateStatus('Đã di chuyển marker');
        });
    }
    map.flyTo({ center: [lng, lat], zoom: 16 });
    syncCoordinates(lat, lng);
}

function syncCoordinates(lat, lng) {
    const component = getPickupComponent();

    if (!component) return;

    component.set('pickupForm.pickup_lat', lat.toFixed(7));
    component.set('pickupForm.pickup_lng', lng.toFixed(7));
}

function updateStatus(text) {
    const status = document.getElementById('pickup-map-status');

    if (status) status.textContent = text;
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

function getGeocodeLabel(result, fallback) {
    return result?.display || result?.display_name || result?.name || result?.address || fallback;
}

function getGeocodeRefId(result) {
    return result?.ref_id || result?.refid || result?.data_new?.ref_id || result?.data_old?.ref_id || null;
}

async function fetchPlaceByRefId(refId) {
    const geocodeApiKey = getGeocodeApiKey();

    if (!geocodeApiKey || !refId) return null;

    try {
        const response = await fetch(
            `https://maps.vietmap.vn/api/place/v4?apikey=${geocodeApiKey}&refid=${encodeURIComponent(refId)}`,
            { headers: { Accept: 'application/json' } }
        );

        if (!response.ok) return null;

        return response.json();
    } catch {
        return null;
    }
}

async function reverseGeocode(lat, lng) {
    const geocodeApiKey = getGeocodeApiKey();

    if (!geocodeApiKey) {
        updateStatus('Chưa cấu hình VITE_VIETMAP_API_KEY');
        return null;
    }

    try {
        const response = await fetch(
            `https://maps.vietmap.vn/api/reverse/v4?apikey=${geocodeApiKey}&lat=${lat}&lng=${lng}&display_type=5`,
            { headers: { Accept: 'application/json' } }
        );

        if (!response.ok) return null;

        const data = await response.json();

        return data;
    } catch {
        return null;
    }
}

async function geocodeAddress() {
    const component = getPickupComponent();
    const componentRoot = getPickupComponentRoot();

    if (!component || !componentRoot) return;

    const form = component.get('pickupForm') || {};
    const address = form.address || '';
    const citySelect = componentRoot.querySelector('select[wire\\:model\\.live="pickupForm.id_city"]');
    const wardSelect = componentRoot.querySelector('select[wire\\:model="pickupForm.id_ward"]');
    const cityName = citySelect?.selectedIndex > 0 ? citySelect.options[citySelect.selectedIndex].text : '';
    const wardName = wardSelect?.selectedIndex > 0 ? wardSelect.options[wardSelect.selectedIndex].text : '';
    const fullAddress = [address, wardName, cityName, 'Vietnam'].filter(Boolean).join(', ');

    if (!address && !cityName) {
        updateStatus('Chưa có địa chỉ để tìm kiếm. Kéo bản đồ rồi bấm Ghim tạm bản đồ để chọn.');
        return;
    }

    updateStatus('Đang tìm kiếm vị trí...');

    const geocodeApiKey = getGeocodeApiKey();

    if (!geocodeApiKey) {
        updateStatus('Chưa cấu hình VITE_VIETMAP_API_KEY');
        return;
    }

    try {
        const response = await fetch(`https://maps.vietmap.vn/api/search/v4?apikey=${geocodeApiKey}&text=${encodeURIComponent(fullAddress)}&focus=${DEFAULT_FOCUS}&display_type=5`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) throw new Error('API error');

        const results = normalizeGeocodeResults(await response.json());

        if (!results || results.length === 0) {
            updateStatus('Không tìm thấy vị trí. Kéo bản đồ rồi bấm Ghim tạm bản đồ để chọn.');
            return;
        }

        const first = results[0];
        const refId = getGeocodeRefId(first);
        const place = await fetchPlaceByRefId(refId);
        const coordinate = getGeocodeCoordinate(place) || getGeocodeCoordinate(first);

        if (!coordinate) {
            updateStatus('Không lấy được tọa độ từ VietMap Place. Kéo bản đồ rồi bấm Ghim tạm bản đồ để chọn.');
            return;
        }

        placeMarker(coordinate.lat, coordinate.lng);
        updateStatus(`Đã tìm thấy: ${getGeocodeLabel(place || first, fullAddress).substring(0, 60)}...`);
    } catch {
        updateStatus('Lỗi khi tìm vị trí. Kéo bản đồ rồi bấm Ghim tạm bản đồ để chọn.');
    }
}

function cleanupMap() {
    clearTimeout(setupTimeout);
    clearTimeout(geocodeTimeout);
    getCenterPinButton()?.remove();

    if (map) {
        map.remove();
        map = null;
        marker = null;
    }
}

function handleDomChange(mutations = []) {
    const mapElement = getMapElement();

    if (!mapElement) {
        cleanupMap();
        observer?.disconnect();
        observer = null;
        return;
    }

    if (
        map &&
        mutations.length > 0 &&
        mutations.every((mutation) => mapElement === mutation.target || mapElement.contains(mutation.target))
    ) {
        return;
    }

    if (mapElement.offsetParent === null) {
        if (map) cleanupMap();
        return;
    }

    if (!map) {
        scheduleSetup();
        return;
    }

    if (mapDragActive) return;

    requestAnimationFrame(() => map?.resize());
}

function init() {
    if (!getMapElement()) {
        cleanupMap();
        observer?.disconnect();
        observer = null;
        return;
    }

    if (!observer) {
        observer = new MutationObserver(handleDomChange);
        observer.observe(document.body, { attributes: true, subtree: true, childList: true });
    }

    handleDomChange();
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
document.addEventListener('modal-open', () => {
    init();
    scheduleSetup(200);
});
document.addEventListener('modal-close', () => setTimeout(handleDomChange));

init();
