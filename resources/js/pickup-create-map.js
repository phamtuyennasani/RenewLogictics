const LEAFLET_CSS_URL = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
const LEAFLET_JS_URL = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
const MAP_ELEMENT_ID = 'pickup-create-map';

let leafletPromise = null;
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

function ensureLeaflet() {
    if (window.L) return Promise.resolve(window.L);
    if (leafletPromise) return leafletPromise;

    if (!document.querySelector(`link[href="${LEAFLET_CSS_URL}"]`)) {
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = LEAFLET_CSS_URL;
        stylesheet.crossOrigin = '';
        document.head.appendChild(stylesheet);
    }

    leafletPromise = new Promise((resolve, reject) => {
        const existingScript = document.querySelector(`script[src="${LEAFLET_JS_URL}"]`);
        const script = existingScript ?? document.createElement('script');

        script.addEventListener('load', () => resolve(window.L), { once: true });
        script.addEventListener('error', reject, { once: true });

        if (!existingScript) {
            script.src = LEAFLET_JS_URL;
            script.crossOrigin = '';
            document.head.appendChild(script);
        }
    }).catch((error) => {
        leafletPromise = null;
        updateStatus('Khong tai duoc ban do. Vui long thu lai.');
        throw error;
    });

    return leafletPromise;
}

function scheduleSetup(delay = 0) {
    clearTimeout(setupTimeout);
    setupTimeout = setTimeout(setupMap, delay);
}

async function setupMap() {
    const mapElement = getMapElement();

    if (!mapElement || map || mapElement.offsetParent === null) return;

    try {
        await ensureLeaflet();
    } catch {
        return;
    }

    if (!document.body.contains(mapElement) || mapElement.offsetParent === null || map) return;

    map = window.L.map(mapElement).setView([10.7769, 106.7009], 12);

    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    map.on('click', ({ latlng }) => {
        placeMarker(latlng.lat, latlng.lng);
        updateStatus('\u0110\u00e3 ch\u1ecdn v\u1ecb tr\u00ed th\u1ee7 c\u00f4ng');
    });

    bindGeocodeButton();
    setTimeout(() => map?.invalidateSize(), 300);

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

    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = window.L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', (event) => {
            const position = event.target.getLatLng();
            syncCoordinates(position.lat, position.lng);
            updateStatus('\u0110\u00e3 di chuy\u1ec3n marker');
        });
    }

    map.setView([lat, lng], 16);
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

function geocodeAddress() {
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
        updateStatus('Ch\u01b0a c\u00f3 \u0111\u1ecba ch\u1ec9 \u0111\u1ec3 t\u00ecm');
        return;
    }

    updateStatus('\u0110ang t\u00ecm ki\u1ebfm v\u1ecb tr\u00ed...');

    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(fullAddress)}&limit=1&countrycodes=vn`, {
        headers: { 'Accept-Language': 'vi' },
    })
        .then((response) => response.json())
        .then((results) => {
            if (!results?.length) {
                updateStatus('Kh\u00f4ng t\u00ecm th\u1ea5y v\u1ecb tr\u00ed. H\u00e3y click tr\u00ean b\u1ea3n \u0111\u1ed3 \u0111\u1ec3 ch\u1ecdn.');
                return;
            }

            placeMarker(parseFloat(results[0].lat), parseFloat(results[0].lon));
            updateStatus(`\u0110\u00e3 t\u00ecm th\u1ea5y: ${results[0].display_name.substring(0, 60)}...`);
        })
        .catch(() => {
            updateStatus('L\u1ed7i khi t\u00ecm v\u1ecb tr\u00ed. H\u00e3y click tr\u00ean b\u1ea3n \u0111\u1ed3 \u0111\u1ec3 ch\u1ecdn.');
        });
}

function cleanupMap() {
    clearTimeout(setupTimeout);
    clearTimeout(geocodeTimeout);

    map?.remove();
    map = null;
    marker = null;
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

    if (!map) scheduleSetup();
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
