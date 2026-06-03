const VIETMAP_GL_VERSION = '6.0.1';
const VIETMAP_GL_CSS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.css`;
const VIETMAP_GL_JS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.js`;
const MAP_ELEMENT_ID = 'pickup-create-map';
const DEFAULT_FOCUS = '10.7769,106.7009';

let vietmapPromise = null;
let map = null;
let marker = null;
let observer = null;
let setupTimeout = null;
let geocodeTimeout = null;

function getMapElement() {
    return document.getElementById(MAP_ELEMENT_ID);
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
    return import.meta.env.VITE_VIETMAP_API_KEY_TITLE;
}

function getGeocodeApiKey() {
    return import.meta.env.VITE_VIETMAP_API_KEY;
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
        style: `https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=${tileApiKey}`,
        center: [106.7009, 10.7769],
        zoom: 12,
    });

    map.on('load', () => {
        updateStatus('Đã tải bản đồ. Hay click trên bản đồ để chọn vị trí.');
        bindGeocodeButton();
        setTimeout(() => map?.resize(), 300);
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

    map.on('click', ({ lngLat }) => {
        placeMarker(lngLat.lat, lngLat.lng);
        updateStatus('Da chon vi tri thu cong');
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

function placeMarker(lat, lng) {
    if (!map) return;

    const el = document.createElement('div');
    el.className = 'pickup-marker';
    el.style.cssText = `
        width: 32px;
        height: 32px;
        background: #dc2626;
        border: 3px solid white;
        border-radius: 50% 50% 50% 0;
        transform: rotate(-45deg);
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        cursor: grab;
    `;
    if (marker) {
        marker.setLngLat([lng, lat]);
    } else {
        marker = new window.vietmapgl.Marker({ element: el, draggable: true })
            .setLngLat([lng, lat])
            .addTo(map);

        el.addEventListener('mousedown', () => {
            el.style.cursor = 'grabbing';
        });
        marker.on('dragstart', () => {
            updateStatus('Dang di chuyen marker...');
        });
        marker.on('drag', () => {
            const lngLat = marker.getLngLat();
            syncCoordinates(lngLat.lat, lngLat.lng);
        });

        marker.on('dragend', () => {
            const lngLat = marker.getLngLat();
            syncCoordinates(lngLat.lat, lngLat.lng);
            updateStatus('Da di chuyen marker');
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
        updateStatus('Chua cau hinh VITE_VIETMAP_API_KEY');
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
        updateStatus('Chưa có địa chỉ để tìm kiếm. Hay click trên bản đồ để chọn.');
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
            updateStatus('Không tìm thấy vị trí. Hay click trên bản đồ để chọn.');
            return;
        }

        const first = results[0];
        const refId = getGeocodeRefId(first);
        const place = await fetchPlaceByRefId(refId);
        const coordinate = getGeocodeCoordinate(place) || getGeocodeCoordinate(first);

        if (!coordinate) {
            updateStatus('Không lấy được tọa độ từ VietMap Place. Hay click trên bản đồ để chọn.');
            return;
        }

        placeMarker(coordinate.lat, coordinate.lng);
        updateStatus(`Đã tìm thấy: ${getGeocodeLabel(place || first, fullAddress).substring(0, 60)}...`);
    } catch {
        updateStatus('Lỗi khi tìm vị trí. Hay click trên bản đồ để chọn.');
    }
}

function cleanupMap() {
    clearTimeout(setupTimeout);
    clearTimeout(geocodeTimeout);

    if (map) {
        map.remove();
        map = null;
        marker = null;
    }
}

function handleDomChange() {
    const mapElement = getMapElement();

    if (!mapElement) {
        cleanupMap();
        observer?.disconnect();
        observer = null;
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
