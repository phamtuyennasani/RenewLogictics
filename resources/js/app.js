import './bootstrap';
import Scrollbar from 'smooth-scrollbar';
Scrollbar.init(document.querySelector('#sidebar-scrollbar'),{});
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