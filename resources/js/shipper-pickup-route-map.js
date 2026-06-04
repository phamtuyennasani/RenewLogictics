const VIETMAP_GL_VERSION = '6.0.1';
const VIETMAP_GL_CSS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.css`;
const VIETMAP_GL_JS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.js`;
const OVERLAY_ID = 'shipper-route-overlay';
const MAP_ID = 'shipper-route-map';
const DEFAULT_CENTER = [106.66817068179284, 10.803866192772915];

let vietmapPromise = null;
let routeMap = null;
let shipperMarker = null;
let pickupMarker = null;
let activePickup = null;

function getTileApiKey() {
    return window.__VIETMAP_CONFIG__?.tileApiKey || import.meta.env.VITE_VIETMAP_API_KEY_TITLE;
}

function getRouteApiKey() {
    return window.__VIETMAP_CONFIG__?.geocodeApiKey || import.meta.env.VITE_VIETMAP_API_KEY;
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

function openOverlay() {
    const overlay = document.getElementById(OVERLAY_ID);
    if (!overlay) return;

    overlay.classList.remove('hidden');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
    sizeMapElement();
}

function closeOverlay() {
    const overlay = document.getElementById(OVERLAY_ID);
    if (!overlay) return;

    overlay.classList.add('hidden');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value || '-';
}

function updateStatus(text) {
    if (text) console.debug('[shipper-route-map]', text);
}

function getLocationButton() {
    return document.getElementById('shipper-route-location');
}

function getNativeNavigationButton() {
    return document.getElementById('shipper-route-native');
}

function setLocationButtonState(label, disabled = false) {
    const button = getLocationButton();
    if (!button) return;

    button.textContent = label;
    button.disabled = disabled;
    button.classList.toggle('opacity-60', disabled);
    button.classList.toggle('cursor-not-allowed', disabled);
}

function setLocationButtonVisible(visible) {
    const button = getLocationButton();
    if (!button) return;

    button.classList.toggle('hidden', !visible);
    button.classList.toggle('flex', visible);
}

function setNativeNavigationVisible(visible) {
    const button = getNativeNavigationButton();
    if (!button) return;

    button.classList.toggle('hidden', !visible);
    button.classList.toggle('flex', visible);
}

function openNativeNavigation() {
    if (!activePickup) {
        updateStatus('Chưa có vị trí pickup để dẫn đường.');
        return;
    }

    const destination = `${activePickup.lat},${activePickup.lng}`;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);

    if (isIOS) {
        window.location.href = `maps://maps.apple.com/?daddr=${destination}&dirflg=d`;
        setTimeout(() => {
            window.location.href = `https://www.google.com/maps/dir/?api=1&destination=${destination}&travelmode=driving`;
        }, 700);
        return;
    }

    window.location.href = `https://www.google.com/maps/dir/?api=1&destination=${destination}&travelmode=driving`;
}

function sizeMapElement() {
    const mapElement = document.getElementById(MAP_ID);
    if (!mapElement) return;

    mapElement.style.position = 'absolute';
    mapElement.style.inset = '0';
    mapElement.style.width = '100%';
    mapElement.style.height = '100dvh';
    mapElement.style.minHeight = '100vh';
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

function parsePickup(button) {
    const lat = Number.parseFloat(button.dataset.pickupLat);
    const lng = Number.parseFloat(button.dataset.pickupLng);

    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;

    return {
        id: button.dataset.pickupId || '',
        code: button.dataset.pickupCode || '',
        company: button.dataset.pickupCompany || '',
        address: button.dataset.pickupAddress || '',
        phone: button.dataset.pickupPhone || '',
        packages: button.dataset.pickupPackages || '',
        weight: button.dataset.pickupWeight || '',
        scheduled: button.dataset.pickupScheduled || '',
        lat,
        lng,
    };
}

function requestCurrentPosition(options) {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Trình duyệt không hỗ trợ định vị.'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => resolve({
                lat: position.coords.latitude,
                lng: position.coords.longitude,
            }),
            (error) => reject(error),
            options
        );
    });
}

async function getCurrentPosition() {
    try {
        return await requestCurrentPosition({ enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
    } catch (error) {
        if (error?.code === 2 || error?.message?.includes('kCLErrorLocationUnknown')) {
            return requestCurrentPosition({ enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
        }

        throw new Error('Không lấy được vị trí shipper. Vui lòng bật định vị cho trình duyệt.');
    }
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

function shipperMarkerElement() {
    const element = document.createElement('div');
    element.style.cssText = `
        position: relative;
        width: 86px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    `;
    element.innerHTML = `
        <span style="
            position:absolute;
            left:50%;
            top:50%;
            width:34px;
            height:34px;
            border-radius:999px;
            background:rgba(37,99,235,.22);
            transform:translate(-50%,-50%);
            animation:shipper-location-pulse 1.8s ease-out infinite;
        "></span>
        <span style="
            position:relative;
            z-index:1;
            display:flex;
            align-items:center;
            justify-content:center;
            width:24px;
            height:24px;
            border:3px solid #fff;
            border-radius:999px;
            background:#2563eb;
            box-shadow:0 12px 26px rgba(37,99,235,.34),0 2px 4px rgba(15,23,42,.18);
        "></span>
        <span style="
            position:absolute;
            left:50%;
            top:36px;
            z-index:2;
            border-radius:999px;
            background:#fff;
            color:#1d4ed8;
            font-size:11px;
            font-weight:800;
            line-height:1;
            padding:5px 9px;
            box-shadow:0 10px 22px rgba(15,23,42,.18);
            transform:translateX(-50%);
            white-space:nowrap;
        ">Bạn</span>
    `;

    return element;
}

function ensureShipperMarkerStyles() {
    if (document.getElementById('shipper-location-marker-style')) return;

    const style = document.createElement('style');
    style.id = 'shipper-location-marker-style';
    style.textContent = `
        @keyframes shipper-location-pulse {
            0% { opacity: .72; transform: translate(-50%, -50%) scale(.7); }
            70% { opacity: 0; transform: translate(-50%, -50%) scale(1.65); }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(1.65); }
        }
    `;
    document.head.appendChild(style);
}

async function initMap() {
    await ensureVietmap();
    sizeMapElement();
    if (routeMap) {
        requestAnimationFrame(() => routeMap?.resize());
        setTimeout(() => routeMap?.resize(), 150);
        return routeMap;
    }
    const tileApiKey = getTileApiKey();
    if (!tileApiKey) throw new Error('Chưa cấu hình VietMap Tile API Key.');
    routeMap = new window.vietmapgl.Map({
        container: MAP_ID,
        style: getVietmapStreetStyleUrl(tileApiKey),
        center: DEFAULT_CENTER,
        zoom: 12,
    });
    routeMap.addControl(new window.vietmapgl.NavigationControl(), 'top-right');
    routeMap.on('error', () => {
        updateStatus('Không tải được tile VietMap. Vui lòng kiểm tra Tile API Key.');
    });

    return new Promise((resolve) => {
        let resolved = false;
        const resolveMapReady = () => {
            if (resolved) return;
            resolved = true;
            sizeMapElement();
            requestAnimationFrame(() => routeMap?.resize());
            setTimeout(() => routeMap?.resize(), 150);
            resolve(routeMap);
        };

        routeMap.on('load', resolveMapReady);
        routeMap.on('styledata', resolveMapReady);
        setTimeout(resolveMapReady, 1200);
    });
}

async function fetchRoute(origin, destination) {
    const apiKey = getRouteApiKey();
    if (!apiKey) throw new Error('Chưa cấu hình VietMap Geocode/Route API Key.');

    const params = new URLSearchParams({
        apikey: apiKey,
        points_encoded: 'false',
        vehicle: 'motorcycle',
    });
    params.append('point', `${origin.lat},${origin.lng}`);
    params.append('point', `${destination.lat},${destination.lng}`);

    const response = await fetch(`https://maps.vietmap.vn/api/route/v3?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) throw new Error('Không gọi được VietMap Route API.');

    const data = await response.json();
    if (data?.code !== 'OK' || !Array.isArray(data?.paths) || !data.paths[0]) {
        throw new Error(data?.messages || 'Không tìm thấy tuyến đường phù hợp.');
    }

    return data.paths[0];
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
    const coordinates = routeCoordinates(path);

    if (routeMap.getLayer('shipper-route-line')) routeMap.removeLayer('shipper-route-line');
    if (routeMap.getSource('shipper-route')) routeMap.removeSource('shipper-route');

    if (coordinates.length) {
        routeMap.addSource('shipper-route', {
            type: 'geojson',
            data: {
                type: 'Feature',
                geometry: { type: 'LineString', coordinates },
                properties: {},
            },
        });
        routeMap.addLayer({
            id: 'shipper-route-line',
            type: 'line',
            source: 'shipper-route',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: { 'line-color': '#0f766e', 'line-width': 5, 'line-opacity': 0.88 },
        });
    }

    shipperMarker?.remove();
    pickupMarker?.remove();
    ensureShipperMarkerStyles();
    shipperMarker = new window.vietmapgl.Marker({ element: shipperMarkerElement(), anchor: 'center' })
        .setLngLat([origin.lng, origin.lat])
        .addTo(routeMap);
    pickupMarker = new window.vietmapgl.Marker({ element: markerElement('#dc2626', 'P') })
        .setLngLat([destination.lng, destination.lat])
        .addTo(routeMap);

    const bounds = new window.vietmapgl.LngLatBounds([origin.lng, origin.lat], [origin.lng, origin.lat]);
    bounds.extend([destination.lng, destination.lat]);
    coordinates.forEach((coordinate) => bounds.extend(coordinate));
    routeMap.fitBounds(bounds, { padding: 72, maxZoom: 16, duration: 600 });
}

function drawPickupOnly(pickup) {
    if (!routeMap) return;

    if (routeMap.getLayer('shipper-route-line')) routeMap.removeLayer('shipper-route-line');
    if (routeMap.getSource('shipper-route')) routeMap.removeSource('shipper-route');

    shipperMarker?.remove();
    shipperMarker = null;
    pickupMarker?.remove();
    pickupMarker = new window.vietmapgl.Marker({ element: markerElement('#dc2626', 'P') })
        .setLngLat([pickup.lng, pickup.lat])
        .addTo(routeMap);

    routeMap.flyTo({ center: [pickup.lng, pickup.lat], zoom: 15, duration: 500 });
}

function drawShipperLocation(location) {
    if (!routeMap || !location) return;

    shipperMarker?.remove();
    ensureShipperMarkerStyles();
    shipperMarker = new window.vietmapgl.Marker({ element: shipperMarkerElement(), anchor: 'center' })
        .setLngLat([location.lng, location.lat])
        .addTo(routeMap);

    if (!activePickup) {
        routeMap.flyTo({ center: [location.lng, location.lat], zoom: 15, duration: 500 });
        return;
    }

    const bounds = new window.vietmapgl.LngLatBounds([location.lng, location.lat], [location.lng, location.lat]);
    bounds.extend([activePickup.lng, activePickup.lat]);
    routeMap.fitBounds(bounds, { padding: 72, maxZoom: 16, duration: 600 });
}

async function requestShipperLocation() {
    if (!routeMap) {
        updateStatus('Bản đồ chưa sẵn sàng.');
        return;
    }

    if (!activePickup) {
        updateStatus('Chưa có vị trí pickup để tìm đường.');
        return;
    }

    setLocationButtonState('Đang tìm...', true);
    updateStatus('Đang xin quyền vị trí shipper...');

    try {
        const location = await getCurrentPosition();
        drawShipperLocation(location);
        updateStatus('Đang tìm đường đến điểm lấy hàng...');

        const path = await fetchRoute(location, activePickup);
        drawRoute(location, activePickup, path);
        fillInfo(activePickup, path);
        setLocationButtonVisible(false);
        setNativeNavigationVisible(true);
        updateStatus('Đã tìm thấy đường đi đến điểm lấy hàng.');
        setLocationButtonState('Tìm lại đường');
    } catch (error) {
        setLocationButtonVisible(true);
        setNativeNavigationVisible(false);
        updateStatus(error?.message || 'Không lấy được vị trí shipper.');
        setLocationButtonState('Tìm đường');
    }
}

function fillInfo(pickup, path = null) {
    setText('shipper-route-code', pickup.code);
    setText('shipper-route-company', pickup.company);
    setText('shipper-route-address', pickup.address);
    setText('shipper-route-phone', pickup.phone);
    setText('shipper-route-packages', pickup.packages);
    setText('shipper-route-weight', pickup.weight);
    setText('shipper-route-scheduled', pickup.scheduled);
    setText('shipper-route-distance', path ? formatDistance(path.distance) : '-');
    setText('shipper-route-duration', path ? formatDuration(path.time) : '-');
}

async function showRoute(button) {
    const pickup = parsePickup(button);

    if (!pickup) {
        alert('Pickup này chưa có tọa độ lấy hàng.');
        return;
    }

    activePickup = pickup;
    openOverlay();
    fillInfo(pickup);
    updateStatus('Đang tải bản đồ...');

    try {
        await initMap();
        routeMap.resize();
        drawPickupOnly(pickup);
        updateStatus('Đã hiển thị vị trí pickup trên bản đồ.');
        setLocationButtonState('Tìm đường');
        setLocationButtonVisible(true);
        setNativeNavigationVisible(false);
    } catch (error) {
        setLocationButtonVisible(true);
        setNativeNavigationVisible(false);
        updateStatus(error?.message || 'Không thể hiển thị tuyến đường.');
        if (routeMap && activePickup) drawPickupOnly(activePickup);
    }
}

function bindRouteButtons() {
    document.querySelectorAll('[data-shipper-route-button]').forEach((button) => {
        if (button.dataset.shipperRouteBound === 'true') return;
        button.dataset.shipperRouteBound = 'true';
        button.addEventListener('click', () => showRoute(button));
    });
}

function bindOverlayControls() {
    const closeButton = document.getElementById('shipper-route-close');
    if (closeButton && closeButton.dataset.shipperRouteBound !== 'true') {
        closeButton.dataset.shipperRouteBound = 'true';
        closeButton.addEventListener('click', closeOverlay);
    }

    const toggleButton = document.getElementById('shipper-route-info-toggle');
    const nativeButton = document.getElementById('shipper-route-native');
    const locationButton = getLocationButton();
    if (locationButton && locationButton.dataset.shipperRouteBound !== 'true') {
        locationButton.dataset.shipperRouteBound = 'true';
        locationButton.addEventListener('click', requestShipperLocation);
    }
    if (nativeButton && nativeButton.dataset.shipperRouteBound !== 'true') {
        nativeButton.dataset.shipperRouteBound = 'true';
        nativeButton.addEventListener('click', openNativeNavigation);
    }

    if (!toggleButton || toggleButton.dataset.shipperRouteBound === 'true') return;

    toggleButton.dataset.shipperRouteBound = 'true';
    toggleButton.addEventListener('click', () => {
        const body = document.getElementById('shipper-route-info-body');
        const label = document.getElementById('shipper-route-info-toggle-label');
        if (!body || !label) return;

        const hidden = body.classList.toggle('hidden');
        label.textContent = hidden ? 'Hiện' : 'Ẩn';
    });
}

function init() {
    bindRouteButtons();
    bindOverlayControls();
}

document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
document.addEventListener('livewire:updated', bindRouteButtons);
