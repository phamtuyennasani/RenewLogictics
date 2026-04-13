<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\Facades\Auth;

class Sidebar extends Component
{
    /**
     * Menu items định nghĩa theo cấu trúc phân quyền
     * Key = slug route, Icon, Label, Roles được phép, Children (nếu có)
     */
    public array $menuItems;

    public function __construct()
    {
        $this->menuItems = $this->buildMenu();
    }
    /**
     * Build menu tree dựa trên role của user đang login
     */
    protected function buildMenu(): array
    {
        $user = Auth::user();
        $role = $user->roles->first()?->name ?? 'guest';
        // === NHÓM TÁC VỤ (visible cho hầu hết role) ===
        $tacvu = $this->filterByRole([
            'label' => 'Tác vụ',
            'items' => [
                [
                    'route'  => 'dashboard',
                    'icon'   => 'dashboard',
                    'label'  => 'Dashboard',
                    'roles'  => ['admin', 'manager', 'ketoan', 'cs', 'sale', 'ops', 'ctv', 'shipper'],
                ],
                [
                    'route'  => 'orders.index',
                    'icon'   => 'orders',
                    'label'  => 'Đơn hàng',
                    'roles'  => ['admin', 'manager', 'ketoan', 'cs', 'sale', 'ops', 'ctv'],
                ],
                [
                    'route'  => 'orders.create',
                    'icon'   => 'create-order',
                    'label'  => 'Tạo đơn nhanh',
                    'roles'  => ['admin', 'cs', 'sale', 'ctv'],
                ],
                [
                    'route'  => 'pickups.index',
                    'icon'   => 'pickup',
                    'label'  => 'Quản lý Pickup',
                    'roles'  => ['admin', 'manager', 'sale', 'cs', 'ops', 'ctv'],
                ],
                [
                    'route'  => 'scan',
                    'icon'   => 'scan',
                    'label'  => 'Quét kiện hàng',
                    'roles'  => ['admin', 'ops'],
                ],
                [
                    'route'  => 'packages.index',
                    'icon'   => 'package',
                    'label'  => 'Quản lý tải hàng',
                    'roles'  => ['admin', 'manager', 'ops', 'cs'],
                ],
                [
                    'route'  => 'congno.index',
                    'icon'   => 'congno',
                    'label'  => 'Công nợ CTV',
                    'roles'  => ['admin', 'manager', 'ketoan', 'ctv'],
                ],
                [
                    'route'  => 'congno.dailyindex',
                    'icon'   => 'congno-daily',
                    'label'  => 'Công nợ đại lý',
                    'roles'  => ['admin', 'manager', 'ketoan'],
                ],
                [
                    'route'  => 'thongke',
                    'icon'   => 'stats',
                    'label'  => 'Thống kê',
                    'roles'  => ['admin', 'manager', 'sale', 'ketoan', 'ctv'],
                ],
            ],
        ], $role);

        // === NHÓM KHÁCH HÀNG ===
        $khachhang = $this->filterByRole([
            'label' => 'Khách hàng',
            'items' => [
                [
                    'route'  => 'customers.index',
                    'icon'   => 'customer',
                    'label'  => 'Khách hàng',
                    'roles'  => ['admin', 'manager', 'cs', 'sale', 'ctv'],
                ],
                [
                    'route'  => 'addresses.index',
                    'icon'   => 'address',
                    'label'  => 'Địa chỉ nhận',
                    'roles'  => ['admin', 'manager', 'cs', 'sale', 'ctv'],
                ],
                [
                    'route'  => 'ctv.index',
                    'icon'   => 'ctv',
                    'label'  => 'Cộng tác viên',
                    'roles'  => ['admin', 'manager', 'cs'],
                ],
            ],
        ], $role);

        // === NHÓM NHÂN SỰ (ẩn với CTV, CS, OPS, SALE) ===
        $nhansu = [];
        if (!in_array($role, ['ctv', 'cs', 'ops', 'sale'])) {
            $nhansu = $this->filterByRole([
                'label' => 'Nhân sự',
                'items' => [
                    [
                        'route'  => 'nhansu.sale',
                        'icon'   => 'sale-list',
                        'label'  => 'Danh sách Sale',
                        'roles'  => ['admin', 'ketoan', 'cs'],
                    ],
                    [
                        'route'  => 'nhansu.internal',
                        'icon'   => 'internal',
                        'label'  => 'Nhân viên nội bộ',
                        'roles'  => ['admin', 'cs'],
                        'children' => [
                            ['route' => 'nhansu.ketoan',  'label' => 'Tài khoản Kế toán',  'roles' => ['admin', 'cs']],
                            ['route' => 'nhansu.cs',        'label' => 'Tài khoản CS',         'roles' => ['admin', 'cs']],
                            ['route' => 'nhansu.ops',       'label' => 'Tài khoản OPS',        'roles' => ['admin', 'cs']],
                            ['route' => 'nhansu.shipper',   'label' => 'Tài khoản Shipper',    'roles' => ['admin', 'cs']],
                        ],
                    ],
                    [
                        'route'  => 'nhansu.manager',
                        'icon'   => 'manager-list',
                        'label'  => 'Nhân sự Quản lý',
                        'roles'  => ['admin', 'sale', 'cs'],
                    ],
                ],
            ], $role);
        }

        // === NHÓM DỮ LIỆU ===
        $dulieu = $this->filterByRole([
            'label' => 'Dữ liệu',
            'items' => [
                // Dịch vụ
                [
                    'route'  => 'dichvu.index',
                    'icon'   => 'service',
                    'label'  => 'Dịch vụ',
                    'roles'  => ['admin', 'cs'],
                    'route_params' => ['type' => 'dich-vu'],
                    'children' => [
                        ['route' => 'dichvu.index', 'route_params' => ['type' => 'dichvuchinh'],            'label' => 'Dịch vụ chính',       'roles' => ['admin', 'cs']],
                        ['route' => 'dichvu.index', 'route_params' => ['type' => 'dichvuchitiet'],   'label' => 'Dịch vụ chi tiết',     'roles' => ['admin', 'cs']],
                        ['route' => 'dichvu.index', 'route_params' => ['type' => 'dichvudikem'],     'label' => 'Dịch vụ đi kèm',      'roles' => ['admin', 'cs']],
                        ['route' => 'dichvu.index', 'route_params' => ['type' => 'chinhanh'],         'label' => 'Chi nhánh nhận hàng', 'roles' => ['admin', 'cs']],
                        ['route' => 'dichvu.index', 'route_params' => ['type' => 'tinhtrangdon'],     'label' => 'Tình trạng đơn',      'roles' => ['admin', 'cs']],
                    ],
                ],
                // Đơn vị
                [
                    'route'  => 'donvi.index',
                    'icon'   => 'unit',
                    'label'  => 'Đơn vị',
                    'roles'  => ['admin', 'cs'],
                    'route_params' => ['type' => 'don-vi'],
                    'children' => [
                        ['route' => 'donvi.index', 'route_params' => ['type' => 'loaikien'], 'label' => 'Loại kiện',              'roles' => ['admin', 'cs']],
                        ['route' => 'donvi.index', 'route_params' => ['type' => 'hanghoa'],  'label' => 'Hàng hóa (Loại kiện)',  'roles' => ['admin', 'cs']],
                    ],
                ],
                // Phân loại
                [
                    'route'  => 'phanloai.index',
                    'icon'   => 'classify',
                    'label'  => 'Phân loại',
                    'roles'  => ['admin', 'cs'],
                    'route_params' => ['type' => 'phan-loai'],
                    'children' => [
                        ['route' => 'phanloai.index', 'route_params' => ['type' => 'loaibuugui'],   'label' => 'Loại bưu gửi',      'roles' => ['admin', 'cs']],
                        ['route' => 'phanloai.index', 'route_params' => ['type' => 'lydoguihang'],  'label' => 'Lý do gửi hàng',     'roles' => ['admin', 'cs']],
                        ['route' => 'phanloai.index', 'route_params' => ['type' => 'hinhthucgui'],  'label' => 'Hình thức gửi hàng', 'roles' => ['admin', 'cs']],
                        ['route' => 'phanloai.index', 'route_params' => ['type' => 'deliveryterm'], 'label' => 'Delivery term',     'roles' => ['admin', 'cs']],
                        ['route' => 'phanloai.index', 'route_params' => ['type' => 'phuongtien'],  'label' => 'Phương tiện',        'roles' => ['admin', 'cs']],
                    ],
                ],
                // Quốc gia
                [
                    'route'  => 'place.index',
                    'icon'   => 'country',
                    'label'  => 'Quốc gia',
                    'roles'  => ['admin', 'cs'],
                    'route_params' => ['type' => 'place'],
                    'children' => [
                        ['route' => 'place.index', 'route_params' => ['type' => 'quocgia'],   'label' => 'Quốc gia',           'roles' => ['admin', 'cs']],
                        ['route' => 'place.index', 'route_params' => ['type' => 'tinhthanh'], 'label' => 'Tỉnh / Thành phố',  'roles' => ['admin', 'cs']],
                        ['route' => 'place.index', 'route_params' => ['type' => 'phuongxa'],  'label' => 'Phường / Xã',        'roles' => ['admin', 'cs']],
                    ],
                ],
                // Đối tác (Đại lý, Hãng bay, Chung chuyển)
                [
                    'route'  => 'doitac.index',
                    'icon'   => 'agency',
                    'label'  => 'Đại lý',
                    'roles'  => ['admin', 'cs'],
                    'route_params' => ['type' => 'doi-tac'],
                    'children' => [
                        ['route' => 'doitac.index', 'route_params' => ['type' => 'daily'], 'label' => 'Đại lý','roles' => ['admin', 'cs']],
                        ['route' => 'doitac.index', 'route_params' => ['type' => 'hangbay'],    'label' => 'Hãng bay','roles' => ['admin', 'cs']],
                        ['route' => 'doitac.index', 'route_params' => ['type' => 'doitac'],    'label' => 'Đối tác chung chuyển','roles' => ['admin', 'cs']],
                    ],
                ],
                // Phụ phí
                [
                    'route'  => 'phuphi.index',
                    'icon'   => 'fee',
                    'label'  => 'Phụ phí',
                    'roles'  => ['admin', 'ketoan'],
                    'route_params' => ['type' => 'phu-phi'],
                    'children' => [
                        ['route' => 'phuphi.index', 'route_params' => ['type' => 'phuphi'], 'label' => 'Phụ phí đơn hàng', 'roles' => ['admin', 'ketoan']],
                    ],
                ],
            ],
        ], $role);

        // === NHÓM CẤU HÌNH ===
        $cautruong = $this->filterByRole([
            'label' => 'Cấu hình',
            'items' => [
                // Chính sách (chỉ Admin)
                [
                    'route'  => 'chinhsach.index',
                    'icon'   => 'policy',
                    'label'  => 'Chính sách',
                    'roles'  => ['admin'],
                    'children' => [
                        ['route' => 'chinhsach.index',              'label' => 'Danh sách chính sách', 'roles' => ['admin']],
                        ['route' => 'chinhsach.quydinh-taodon',     'label' => 'Quy định tạo đơn',     'roles' => ['admin']],
                        ['route' => 'chinhsach.quydinh-khahang',    'label' => 'Quy định khai hàng',   'roles' => ['admin']],
                        ['route' => 'chinhsach.quydinh-themtai',   'label' => 'Quy định thêm tải',    'roles' => ['admin']],
                        ['route' => 'chinhsach.dieukhoan',          'label' => 'Chính sách & Điều khoản', 'roles' => ['admin']],
                        ['route' => 'chinhsach.baomat',             'label' => 'Bảo mật thông tin',    'roles' => ['admin']],
                    ],
                ],
                // Cấu hình chung
                [
                    'route'  => 'settings.index',
                    'icon'   => 'settings',
                    'label'  => 'Cấu hình chung',
                    'roles'  => ['admin', 'manager', 'ketoan', 'cs'],
                    'children' => [
                        ['route' => 'settings.index',        'label' => 'Danh sách cấu hình',  'roles' => ['admin', 'manager', 'ketoan', 'cs']],
                        ['route' => 'settings.thongbao',   'label' => 'Thông báo',           'roles' => ['admin', 'manager', 'ketoan', 'cs']],
                        ['route' => 'settings.logo',        'label' => 'Logo',                 'roles' => ['admin']],
                        ['route' => 'settings.favicon',    'label' => 'Favicon',              'roles' => ['admin']],
                        ['route' => 'settings.banner',     'label' => 'Banner đăng nhập',     'roles' => ['admin']],
                        ['route' => 'settings.social',      'label' => 'Social',                'roles' => ['admin']],
                        ['route' => 'settings.company',     'label' => 'Thông tin công ty',    'roles' => ['admin']],
                        ['route' => 'profile',             'label' => 'Thông tin cá nhân',     'roles' => ['admin', 'manager', 'ketoan', 'cs', 'sale', 'ops', 'ctv', 'shipper']],
                    ],
                ],
            ],
        ], $role);

        return array_filter([
            'tacvu'      => $tacvu,
            'khachhang'  => $khachhang,
            'nhansu'     => $nhansu,
            'dulieu'     => $dulieu,
            'cau_hinh'   => $cautruong,
        ]);
    }

    /**
     * Build children cho mục Trạng thái (có thêm điều kiện ketoan)
     */
    protected function buildStatusChildren(string $role): array
    {
        $items = [];

        if ($role !== 'ketoan') {
            $items[] = ['route' => 'dulieu.index', 'route_params' => ['type' => 'trangthai-xuly'],     'label' => 'Trạng thái xử lý',    'roles' => ['admin', 'ketoan']];
            $items[] = ['route' => 'dulieu.index', 'route_params' => ['type' => 'trangthai-taihang'],  'label' => 'Trạng thái tải hàng', 'roles' => ['admin', 'ketoan']];
            $items[] = ['route' => 'dulieu.index', 'route_params' => ['type' => 'trangthai-pickup'],   'label' => 'Trạng thái pickup',    'roles' => ['admin', 'ketoan']];
        }

        $items[] = ['route' => 'dulieu.index', 'route_params' => ['type' => 'trangthai-ttkhachhang'], 'label' => 'KH thanh toán',      'roles' => ['admin', 'ketoan']];
        $items[] = ['route' => 'dulieu.index', 'route_params' => ['type' => 'trangthai-ttncc'],        'label' => 'Thanh toán NCC',       'roles' => ['admin', 'ketoan']];
        $items[] = ['route' => 'dulieu.index', 'route_params' => ['type' => 'trangthai-ttcongno'],     'label' => 'Thanh toán công nợ',  'roles' => ['admin', 'ketoan']];

        return $items;
    }

    /**
     * Lọc items theo role hiện tại
     */
    protected function filterByRole(array $group, string $role): array
    {
        $filteredItems = [];

        foreach ($group['items'] ?? [] as $item) {
            // Filter children trước
            if (!empty($item['children'])) {
                $item['children'] = array_values(array_filter(
                    $item['children'],
                    fn($child) => $this->roleMatch($child['roles'] ?? [], $role)
                ));
                // Chỉ giữ lại parent nếu có ít nhất 1 child
                if (!empty($item['children'])) {
                    $filteredItems[] = $item;
                }
            } else {
                // Item không có children → kiểm tra trực tiếp
                if ($this->roleMatch($item['roles'] ?? [], $role)) {
                    $filteredItems[] = $item;
                }
            }
        }

        return empty($filteredItems) ? [] : [
            'label' => $group['label'],
            'items' => $filteredItems,
        ];
    }

    /**
     * Kiểm tra user role có trong danh sách allowed roles không
     */
    protected function roleMatch(array $allowedRoles, string $userRole): bool
    {
        if (empty($allowedRoles)) {
            return true;
        }
        return in_array($userRole, $allowedRoles);
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('components.sidebar.sidebar');
    }
}
