import TomSelect from 'tom-select';

window.TomSelect = TomSelect;

const DEFAULT_SELECTOR = 'select.tomselectEml';

function renderDefaultOption(data, escape) {
    return "<div class='!rounded-none'><p class='!line-clamp-1 mb-0'>" + escape(data.text || '') + '</p></div>';
}

function renderDefaultItem(data, escape) {
    return "<div><p class='!line-clamp-1 mb-0'>" + escape(data.text || '') + '</p></div>';
}

function buildTomSelectOptions(select) {
    const placeholder = select.getAttribute('data-placeholder') || '';
    const template = select.getAttribute('data-template') || 'default';
    const livewireModel = select.getAttribute('data-livewire-model');
    const livewireLive = select.getAttribute('data-livewire-live') !== 'false';
    let isBooting = true;
    let renderOption = renderDefaultOption;
    let renderItem = renderDefaultItem;
    if (template === 'custom-sender') {
        renderItem = function (data, escape) {
            const dataAttr = select.querySelector(`option[value="${data.value}"]`)?.getAttribute('data-attr');
            console.log(JSON.parse(dataAttr));
            if(dataAttr==null) {
                return renderDefaultItem(data, escape);
            }
            const parsedData = JSON.parse(dataAttr);
            return "<div><p class='!line-clamp-1 mb-0'><b>AccNo.</b> " + escape(parsedData.code || '') + ' - ' + escape(parsedData.company_name || '') + ' - <b>' + escape(parsedData.phone || '') + '</b> - ' + escape(parsedData.address || '') + '</p></div>';
        };
    }
    if (template === 'custom-receiver') {
        renderItem = function (data, escape) {
            const dataAttr = select.querySelector(`option[value="${data.value}"]`)?.getAttribute('data-attr');
            if(dataAttr==null) {
                return renderDefaultItem(data, escape);
            }
            const parsedData = JSON.parse(dataAttr);
            return "<div><p class='!line-clamp-1 mb-0'><b>"+ escape(parsedData.company || '') + '</b> - <b>' + escape(parsedData.phone || '') + '</b> - ' + escape(parsedData.postcode || '') +' - ' + escape(parsedData.address || '') + '</p></div>';
        };
    }
    if (template === 'custom-danhsachgui') {
        renderItem = function (data, escape) {
            const dataAttr = select.querySelector(`option[value="${data.value}"]`)?.getAttribute('data-attr');
            if(dataAttr==null) {
                return renderDefaultItem(data, escape);
            }
            const parsedData = JSON.parse(dataAttr);
            return "<div><p class='!line-clamp-1 mb-0'><b>" + escape(parsedData.company_name || '') + '</b> - ' + escape(parsedData.phone || '') + ' - ' + escape(parsedData.address || '') + '</p></div>';
        };
    }
    return {
        plugins: ['dropdown_input'],
        placeholder,
        render: {
            option: renderDefaultOption,
            item: renderItem,
        },
        onInitialize: function () {
            isBooting = false;
            select.dataset.tomselectInit = 'true';
        },
        onChange: function (value) {
            if (isBooting) return;
            select.value = value || '';
            if (livewireModel) {
                const componentEl = select.closest('[wire\\:id]');
                const componentId = componentEl?.getAttribute('wire:id');

                if (componentId && window.Livewire?.find) {
                    window.Livewire.find(componentId)?.set(livewireModel, value || null, livewireLive);
                }
            }
            select.dispatchEvent(new Event('change', { bubbles: true }));
        },
    };
}

export function initTomSelectEml(container = document, selector = DEFAULT_SELECTOR) {
    container.querySelectorAll(selector).forEach((select) => {
        if (!(select instanceof HTMLSelectElement)) return;
        if (select.tomselect) return;

        new TomSelect(select, buildTomSelectOptions(select));
    });
}

export function destroyTomSelectEml(container = document, selector = DEFAULT_SELECTOR) {
    container.querySelectorAll(selector).forEach((select) => {
        if (!(select instanceof HTMLSelectElement)) return;
        select.tomselect?.destroy();
        delete select.dataset.tomselectInit;
    });
}

export function reinitTomSelectEml(container = document, selector = DEFAULT_SELECTOR) {
    destroyTomSelectEml(container, selector);
    initTomSelectEml(container, selector);
}

export function registerTomSelectNavigation() {
    document.addEventListener('livewire:initialized', () => {
        initTomSelectEml();
    });

    document.addEventListener('livewire:navigating', () => {
        destroyTomSelectEml();
    });

    document.addEventListener('livewire:navigated', () => {
        initTomSelectEml();
    });
}

window.TomSelectHelper = {
    init: initTomSelectEml,
    destroy: destroyTomSelectEml,
    reinit: reinitTomSelectEml,
    registerNavigation: registerTomSelectNavigation,
};
