import './bootstrap';
import TomSelect from 'tom-select';
window.TomSelect = TomSelect;
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

// Bắt đầu lắng nghe sự kiện điều hướng của Livewire 
document.addEventListener('livewire:navigate', (event) => {})
// Lắng nghe sự kiện trong quá trình điều hướng
document.addEventListener('livewire:navigating', (e) => {});
// Lắng nghe sự kiện sau khi điều hướng hoàn tất
document.addEventListener('livewire:navigated', () => {});

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
                    this.updateOptions(e.detail.options);
                });
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