import './bootstrap';
import '../../vendor/power-components/livewire-powergrid/dist/powergrid';
import TomSelect from 'tom-select';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import Chart from 'chart.js/auto';
import { registerTomSelectNavigation } from './tom-select-helper';
import { Fancybox } from '@fancyapps/ui';
import '@fancyapps/ui/dist/fancybox/fancybox.css';
import './pickup-create-map';

window.TomSelect = TomSelect;
window.flatpickr = flatpickr;
window.Chart = Chart;
window.Fancybox = Fancybox;
window.loadZXingBrowser = async () => {
    const [browser, library] = await Promise.all([
        import('@zxing/browser'),
        import('@zxing/library'),
    ]);

    return { ...browser, DecodeHintType: library.DecodeHintType };
};
Fancybox.bind('[data-fancybox]', {});
import Scrollbar from 'smooth-scrollbar';
const sidebarEl = document.querySelector('#sidebar-scrollbar');
if (sidebarEl) {
    Scrollbar.init(sidebarEl, {});
}
window.SidebarData = function(){
    return {
        mobileOpen: false,
        expandedGroups: {},
        currentPath: window.location.pathname,
    }
};

document.addEventListener('alpine:init', () => {
    Alpine.data('selectSearch', (config) => ({
        tomSelectInstance: null,
        propertyName: config.propertyName,
        init() {
            this.$nextTick(() => {
                const el = this.$refs.select;
                if (el.dataset.tomselectInit === 'true') return;
                this.tomSelectInstance = new TomSelect(el, {
                    create: false,
                    sortField: { field: 'text', direction: 'asc' },
                    plugins: ['dropdown_input'],
                    placeholder: config.placeholder,
                    options: config.initialOptions,
                    items: this.$wire.get(this.propertyName)
                        ? [String(this.$wire.get(this.propertyName))]
                        : [],
                    render: {
                        no_results: function() {
                            return '<div class="no-results">Không tìm thấy kết quả</div>';
                        }
                    },
                    onChange: (value) => {
                        this.$wire.set(this.propertyName, value || null);
                    }
                });
                if (config.disabled) {
                    this.tomSelectInstance.disable();
                }
                // Watch value thay đổi từ Livewire
                this.$wire.watch(this.propertyName, (newValue) => {
                    const currentValue = this.tomSelectInstance.getValue();
                    if (currentValue != newValue) {
                        this.tomSelectInstance.setValue(newValue || '', true);
                    }
                });
                this.$el.addEventListener('update-options', (e) => {
                    this.updateOptions(e.detail.options || []);
                });

                this.$el.addEventListener('focus-select-search', () => {
                    this.tomSelectInstance?.focus();
                });

                this.$el.addEventListener('open-select-search', () => {
                    this.tomSelectInstance?.open();
                });

                if ((config.initialOptions || []).length === 0) {
                    this.tomSelectInstance.clear(true);
                    this.tomSelectInstance.clearOptions();
                    this.tomSelectInstance.refreshOptions(false);
                }
                this.$el.addEventListener('update-disabled', (e) => {
                    if (e.detail.disabled) {
                        this.tomSelectInstance.disable();
                    } else {
                        this.tomSelectInstance.enable();
                    }
                });
            });
        },
        updateOptions(newOptions) {
            if (!this.tomSelectInstance) return;
            const currentValue = this.tomSelectInstance.getValue();
            this.tomSelectInstance.clear(true);      // clear selection, silent
            this.tomSelectInstance.clearOptions();    // xóa options cũ
            this.tomSelectInstance.addOptions(newOptions); // thêm options mới
            this.tomSelectInstance.refreshOptions(false);
            const stillExists = newOptions.some(opt => opt.value == currentValue);
            if (stillExists && currentValue) {
                this.tomSelectInstance.setValue(currentValue, true);
            }
        },
        destroy() {
            this.tomSelectInstance?.destroy();
        }
    }));
});

registerTomSelectNavigation();
