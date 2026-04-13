# KẾ HOẠCH CHUYỂN ĐỔI HỆ THỐNG NINA SANG LARAVEL + BLADE

## Context

**Vấn đề:** Hệ thống NINA hiện tại viết bằng PHP thuần (custom NINACORE framework), với nhiều lỗi bảo mật nghiêm trọng (5 CRITICAL issues), code rất lớn (controller 1,500+ dòng), khó bảo trì.
**Mục tiêu:** Rewrite hoàn toàn sang Laravel 13 + Blade, RESTful API, giữ nguyên database, dùng Spatie Permission, có public API.
**Phạm vi:** ~40 tables, 22 controllers, 8 roles, 8 feature modules chính.

### Các quyết định đã xác nhận:
- ✅ **Frontend:** Laravel Blade (tradition) — nhanh hơn Inertia/Vue
- ✅ **API:** RESTful mới (không giữ URL cũ)
- ✅ **Public API:** Cần hỗ trợ mobile app và tích hợp bên thứ 3
- ✅ **Database:** Giữ nguyên prefix `table_`, giữ nguyên schema

---

### Dùng lại database cũ
Thông tin cấu hình database:
- Database name: newvaupost
- Username: root
- Password: root

## File SQL database hiện tại: database.sql

## TỔNG QUAN KIẾN TRÚC MỚI

```
hethong-laravel/
├── app/
│   ├── Console/Commands/        # Artisan jobs
│   ├── Http/
│   │   ├── Controllers/         # Traditional MVC controllers
│   │   │   ├── AuthController.php
│   │   │   ├── OrderController.php
│   │   │   ├── PickupController.php
│   │   │   ├── PackageController.php
│   │   │   ├── CongNoController.php
│   │   │   ├── CongNoDaiLyController.php
│   │   │   ├── ReportController.php
│   │   │   ├── UserController.php
│   │   │   ├── MemberController.php
│   │   │   ├── NewsController.php
│   │   │   └── TrackingController.php
│   │   ├── Middleware/          # Auth, permission middleware
│   │   ├── Requests/            # Form Request validation
│   │   └── Resources/           # API Resources (JSON transform)
│   ├── Models/                   # Eloquent models
│   │   ├── User.php
│   │   ├── Order.php
│   │   ├── Pickup.php
│   │   ├── Package.php
│   │   ├── CongNo.php
│   │   ├── Member.php
│   │   └── News.php
│   ├── Services/                 # Shared business logic
│   │   ├── OrderService.php
│   │   ├── PickupService.php
│   │   ├── TrackingMoreService.php
│   │   ├── ExcelExportService.php
│   │   └── QRBarcodeService.php
│   └── Exceptions/
├── resources/views/              # Blade templates
│   ├── layouts/                  # Master layouts
│   │   ├── app.blade.php
│   │   └── auth.blade.php
│   ├── auth/
│   │   ├── login.blade.php
│   │   └── profile.blade.php
│   ├── orders/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── show.blade.php
│   ├── pickups/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── packages/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   ├── congno/
│   │   ├── customer.blade.php
│   │   └── supplier.blade.php
│   ├── reports/
│   │   └── index.blade.php
│   ├── master-data/
│   │   ├── users.blade.php
│   │   ├── members.blade.php
│   │   └── news.blade.php
│   └── tracking/
│       └── public.blade.php
├── routes/
│   ├── web.php                  # Blade routes (authenticated)
│   └── api.php                  # Public REST API
└── tests/
    ├── Unit/
    └── Feature/
```

**Nguyên tắc thiết kế:**
- Laravel Blade → Server-side rendering, nhanh và đơn giản
- RESTful API với Laravel Sanctum cho authentication
- **Cấu trúc MVC truyền thống Laravel** — flat, không theo domain module
- Service Layer cho logic phức tạp (tách riêng thư mục `Services/`)
- Policy-based authorization cho tất cả resources
- API Resources cho JSON transform (public API)

---

## GIAI ĐOẠN 1: Setup & Database (Tuần 1-2)

### 1.1 Tạo Laravel Project
```bash
composer create-project laravel/laravel hethong-laravel
cd hethong-laravel
composer require spatie/laravel-permission
composer require laravel/sanctum
composer require maatwebsite/excel
composer require intervention/intervention-image
composer require barryvdh/laravel-dompdf
composer require guzzlehttp/guzzle
composer require barryvdh/laravel-debugbar  # dev
composer require laravelcollective/html

# DataTables + Frontend
composer require yajra/laravel-datatables-oracle
npm install
npm install tailwindcss @tailwindcss/vite
npm install datatables.net-bs5  # jQuery DataTables BS5
```

### 1.1 Config TailwindCSS
```js
// vite.config.ts
import tailwindcss from '@tailwindcss/vite'
export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
})
```

```css
/* resources/css/app.css */
@import "tailwindcss";

```

```js
// resources/js/app.js (không có Vue, chỉ init Tailwind)
// import './bootstrap';
import '../css/app.css';
```

```php
<!-- resources/views/layouts/app.blade.php -->
@vite(['resources/css/app.css'])
```

### 1.2 Config DataTables
```bash
php artisan vendor:publish --tag=datatables
```

```php
// config/datatables.php (tùy chỉnh nếu cần)
// Mặc định đã có sẵn, chỉ cần extend nếu cần custom
```

```php
// app/Providers/AppServiceProvider.php
use Yajra\DataTables\Html\Column;

DataTables::useAjax();

public function boot(): void
{
    // Global setting cho tất cả DataTables
    DataTables::usingBv5(); // Bootstrap 5 theme
}
```

Viết migration từ schema hiện tại. **KHÔNG tạo bảng mới — chỉ tạo migration file để Laravel quản lý schema.**

```bash
# Tạo migration cho từng table theo thứ tự:
# 1. reference tables trước (news, province, wards, city, news_list/cat/item/sub)
# 2. core tables (user, member)
# 3. business tables (orders, packages, pickup, congnos)
# 4. sub tables (order_package, order_history, order_notes, etc.)
```

### 1.2 Tạo Models với Relationships

**Đặc biệt lưu ý:**
- `User` model: dùng Spatie Permission traits thay cho `HasPermission` cũ
- `Order` model: JSON casts cho `dichvu`, `info_receiver`, `info_sender`, `payment`, v.v.
- `Member` model: `uuid` làm route key
- `News` model: polymorphic để dùng cho nhiều loại (status, service, country...)

```php
// app/Models/Order.php
class Order extends Model {
    protected $table = 'orders';  // Không có prefix trong Model
    protected $casts = [
        'dichvu' => 'array',
        'info_receiver' => 'array',
        'info_sender' => 'array',
        'info_pickup' => 'array',
        'info_ctv' => 'array',
        'payment' => 'array',
        'last_update' => 'array',
        'last_update_info' => 'array',
        'last_update_dichvu' => 'array',
        'last_update_pickup' => 'array',
    ];
}
```

### 1.3 Config database prefix
```php
// config/database.php
'migrations' => 'table_migrations',  // giữ nguyên prefix 'table_'
```

---

## GIAI ĐOẠN 2: Authentication & Authorization (Tuần 2-3)

### 2.1 Auth với Laravel Sanctum
```php
// Cài đặt: php artisan install:api
// Sanctum cho API token auth + session cho web
```

### 2.2 Setup Spatie Permission

**Migrate pivot tables:**
```php
// Tạo migration cho Spatie permission tables
// model_has_roles, model_has_permissions, role_has_permissions, roles, permissions
```

**Chuyển đổi permission cũ:**
```php
// Quyền cũ format: "{module}.{type}.{action}"
// Ví dụ: "orders.shipping.view", "orders.payment.edit"
// Chuyển sang format Spatie

// Role mapping giữ nguyên:
ADMIN     = 'admin'
MANAGER   = 'manager'
KETOAN    = 'ketoan'
CS        = 'cs'
SALE      = 'sale'
OPS       = 'ops'
CTV       = 'ctv'
SHIPPER   = 'shipper'
```

### 2.3 Middleware chuyển đổi

| Cũ | Laravel mới |
|-----|-------------|
| `PermissionAdmin` middleware | Dùng Gates + Policy |
| `Login` middleware | `auth` + `verified` |
| `NotLogin` middleware | `guest` |
| `Pickup` middleware | `PickupPolicy` |
| `CongNo` middleware | `CongNoPolicy` |
| `CreateOrder` middleware | `CreateOrderPolicy` |
| `CsrfVerifier` | `VerifyCsrfToken` built-in |

### 2.4 Login Controller
```php
// Giữ nguyên logic cũ:
// - Remember token format: {random}|{id}|{username}
// - Redirect shipper → pickup page
// - Redirect others → manager dashboard
```

---

## GIAI ĐOẠN 3: Order Module (Tuần 3-6) — **QUAN TRỌNG NHẤT**

### 3.1 Phân rã Controller

`ApiOrder.php` (1,497 lines) → chia thành:

```
app/Http/Controllers/Order/
├── OrderController.php          # Form tạo đơn, submit, view, payment
├── OrderApiController.php       # API DataTable, status updates
├── OrderPackageController.php   # Package CRUD (3 stages)
├── OrderPhotoController.php     # Upload ảnh
├── OrderTrackingController.php  # Tracking history
└── OrderExportController.php    # Invoice export

app/Services/
├── OrderService.php             # CRUD, code generation AVN{date}{seq}
├── StatusTransitionService.php  # FSM trạng thái đơn
├── WeightCalculationService.php  # Volumetric, chargeable, rounding
└── PaymentCalculationService.php # Cuocban, cuocvon, VAT, hoahong
```

### 3.2 Order Code Generation
```php
// Format: AVN{YYMMDD}{NNNN}
// Sequence per day, reset midnight
// Implement: Redis counter hoặc DB lock
```

### 3.3 Order Status FSM
```php
// Trạng thái hợp lệ:
// MOI_TAO → DA_XAC_NHAN → DA_NHAN_HANG → DUYET_XUAT_HANG
// → DANG_PHAT_HANG → DA_GIAO
// + HUY, RETURN_ORDER, CAUTION, CUSTOM_RELEASING, CAP_BEN
// Validate transitions trong StatusTransitionService
```

### 3.4 Weight Calculation Service
```php
// v_weight = (D × R × C) / dim_factor
// c_weight = max(v_weight, g_weight)
// Rounding: <21kg → 0.5 increments, ≥21kg → ceil
```

### 3.5 API Endpoints

```
POST   /api/orders                    # Tạo đơn
GET    /api/orders                    # DataTable list
GET    /api/orders/{id}                # Chi tiết
PUT    /api/orders/{id}                # Update
PATCH  /api/orders/{id}/status        # Update status
PATCH  /api/orders/{id}/payment       # Update payment
DELETE /api/orders/{id}                # Xóa (soft delete nếu cần)

# Packages
GET    /api/orders/{id}/packages      # 3 stage packages
PATCH  /api/orders/{id}/packages/{stage}/weight

# Notes & Photos
GET    /api/orders/{id}/notes
POST   /api/orders/{id}/notes
DELETE /api/orders/{id}/photos/{photoId}

# Tracking
GET    /api/orders/{id}/tracking
POST   /api/orders/{id}/tracking

# Export
GET    /api/orders/{id}/invoice       # PDF/Excel invoice
```

---

## GIAI ĐOẠN 4: Các Module Còn Lại (Tuần 6-10)

### 4.1 Pickup Module
```
Controllers: PickupController, PickupApiController
Service: PickupService (code PICK{random8})
Code: /pickup, /api/pickup/*
```

### 4.2 Package Module
```
Controllers: PackageController, PackageApiController
Service: PackageService, QRBarcodeService
Code: /packages, /api/packages/*
Pivot: packages_detail (order ↔ package)
```

### 4.3 CongNo Module (2 systems)
```
CongNoController      → Customer debt (CTV)
CongNoDaiLyController → Supplier debt (NCC)
Invoice code: DEB{from}{to}{random}
```

### 4.4 Report Module
```
ReportService + Maatwebsite\Excel
5 format Excel: Admin, Manager, Ketoan, Sale, OPS, CS
```

### 4.5 Master Data Module
```
NewsController → 13+ loại data (status, service, geo, etc.)
UserController → Staff management với auto code
MemberController → Customer management
PlaceController → Province, Ward, City
```

### 4.6 Tracking Public Page
```
TrackingController (public)
Gọi TrackingMoreService
Không cần auth
```

---

## GIAI ĐOẠN 5: Blade Templates (Tuần 3-10)

### 5.1 Blade Structure
```
resources/views/
├── layouts/
│   ├── app.blade.php         # Master layout (sidebar, header, footer)
│   └── auth.blade.php        # Auth layout (centered)
├── components/               # Blade components
│   ├── datatable.blade.php
│   ├── modal.blade.php
│   ├── status-badge.blade.php
│   ├── file-upload.blade.php
│   └── address-form.blade.php
├── orders/
│   ├── index.blade.php      # DataTable list
│   ├── create.blade.php     # Tạo đơn form
│   └── show.blade.php       # Chi tiết + tabs
├── pickups/
├── packages/
├── congno/
├── reports/
├── master-data/
│   ├── users.blade.php
│   ├── members.blade.php
│   └── news.blade.php
└── tracking/
    └── public.blade.php
```

### 5.2 DataTable Component (Yajra DataTables)

#### Controller Example
```php
// app/Http/Controllers/Order/OrderController.php
use Yajra\DataTables\DataTables;
use App\Models\Order;

public function index(Request $request)
{
    if ($request->ajax()) {
        $query = Order::query();

        // Filter theo quyền user
        if (!auth()->user()->hasRole('admin')) {
            $query->where('created_by', auth()->id());
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('action', function($order) {
                return view('orders.partials.actions', compact('order'))->render();
            })
            ->editColumn('status', function($order) {
                return view('components.status-badge', ['status' => $order->status])->render();
            })
            ->editColumn('created_at', function($order) {
                return $order->created_at->format('d/m/Y H:i');
            })
            ->rawColumns(['action', 'status'])
            ->make(true);
    }

    return view('orders.index');
}
```

#### Blade Template (index.blade.php)
```blade
{{-- resources/views/orders/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Danh sách đơn hàng')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.tailwindcss.min.css">
@endpush

@section('content')
<div class="p-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Danh sách đơn hàng</h1>
        <a href="{{ route('orders.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">
            + Tạo đơn mới
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <table id="orders-table" class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Mã đơn</th>
                    <th class="px-4 py-3 text-left">Người nhận</th>
                    <th class="px-4 py-3 text-left">Điện thoại</th>
                    <th class="px-4 py-3 text-left">Trạng thái</th>
                    <th class="px-4 py-3 text-left">Ngày tạo</th>
                    <th class="px-4 py-3 text-left"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.tailwindcss.min.js"></script>
<script>
$(function() {
    $('#orders-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('orders.index') }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false },
            { data: 'ma_don', name: 'ma_don' },
            { data: 'receiver_name', name: 'info_receiver' },
            { data: 'receiver_phone', name: 'info_receiver' },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/vi.json'
        }
    });
});
</script>
@endpush
```

#### Actions Partial (partials/actions.blade.php)
```blade
<div class="flex gap-2">
    <a href="{{ route('orders.show', $order->id) }}" class="text-blue-600 hover:underline">Xem</a>
    <a href="{{ route('orders.edit', $order->id) }}" class="text-green-600 hover:underline">Sửa</a>
    @can('delete', $order)
    <form action="{{ route('orders.destroy', $order->id) }}" method="POST" class="inline">
        @csrf @method('DELETE')
        <button type="submit" class="text-red-600 hover:underline"
            onclick="return confirm('Xóa đơn này?')">Xóa</button>
    </form>
    @endcan
</div>
```

### 5.3 Danh sách DataTable Templates cần migrate

11 templates từ hệ thống cũ cần migrate lên Yajra DataTables:

| # | Template cũ | Module mới | Ghi chú |
|---|-------------|-----------|---------|
| 1 | `manager/index.blade.php` | Orders | Main list đơn hàng |
| 2 | `manager/shipper.blade.php` | Orders | View của shipper |
| 3 | `pickup/index.blade.php` | Pickup | List pickup |
| 4 | `pickup/shipper.blade.php` | Pickup | View của shipper |
| 5 | `congno/index.blade.php` | CongNo | List công nợ KH |
| 6 | `congno/detail.blade.php` | CongNo | Chi tiết công nợ KH |
| 7 | `congnodaily/index.blade.php` | CongNo | List công nợ ĐL |
| 8 | `congnodaily/detail.blade.php` | CongNo | Chi tiết công nợ ĐL |
| 9 | `packages/components/orders.blade.php` | Package | Orders trong lô |
| 10 | `packages/components/packages.blade.php` | Package | List lô hàng |
| 11 | `packages/detail.blade.php` | Package | Chi tiết lô hàng |

**Tổng hợp theo module Laravel:**

| Module | Số template | Tính năng |
|--------|-------------|-----------|
| Orders | 2 | List đơn, filter, actions CRUD |
| Pickup | 2 | List pickup, filter ngày |
| CongNo | 4 | List công nợ KH + ĐL, chi tiết |
| Package | 3 | List lô, orders trong lô |

**Lưu ý khi migrate:**
- DataTables cũ dùng Alpine.js → chuyển sang Yajra server-side
- Cấu hình `tableConfig` trong Alpine → chuyển sang `$->editColumn()` trong Controller
- Route data: `BASE + 'assets/datatables/vi.json'` → CDN language file
- FixedColumns (nếu có) → dùng `fixedHeader` hoặc `fixedColumns` extension của Yajra

### 5.4 Layout Master (app.blade.php)
```blade
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - NINA</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        @include('layouts.partials.sidebar')
        <div class="flex-1 flex flex-col overflow-hidden">
            @include('layouts.partials.header')
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    @stack('scripts')
</body>
</html>
```

### 5.4 Routes cho Blade
```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{id}/edit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::put('/orders/{id}', [OrderController::class, 'update'])->name('orders.update');

    // Pickups
    Route::resource('pickups', PickupController::class);

    // Packages
    Route::resource('packages', PackageController::class);

    // CongNo
    Route::resource('cong-no', CongNoController::class);

    // Reports
    Route::resource('reports', ReportController::class);

    // Master Data
    Route::resource('master-data.users', UserController::class);
    Route::resource('master-data.members', MemberController::class);
    Route::resource('master-data.news', NewsController::class);
});

// Public Tracking (no auth)
Route::get('/tracking/{billCode}', [TrackingController::class, 'public'])
    ->name('tracking.public');

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
```

---

## GIAI ĐOẠN 6: Public API (Tuần 4+)

### 6.1 API Authentication với Sanctum
```php
// routes/api.php
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware(['throttle:60,1'])->group(function () {
    // Public tracking
    Route::get('/tracking/{billCode}', [TrackingApiController::class, 'show']);

    // Authenticated API
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::apiResource('orders', OrderApiController::class);
        Route::get('/orders/{id}/invoice', [OrderApiController::class, 'invoice']);
        Route::apiResource('pickups', PickupApiController::class);
    });
});
```

---

## GIAI ĐOẠN 7: Fix Security Issues (Song song)

### 7.1 CRITICAL Fixes từ code review
| Issue | Fix |
|-------|-----|
| Hardcoded secret key | `.env` + `php artisan key:generate` |
| IDOR in invoice export | Thêm Policy `viewInvoice()` check ownership |
| SQL injection via table param | Whitelist validation trong API |
| Missing auth on write APIs | Middleware bắt buộc auth + CSRF |
| Unbounded DoS queries | Thêm pagination + query limits |

### 7.2 Security Improvements mới
- Rate limiting trên login: `throttle:5,1`
- Account lockout sau nhiều lần đăng nhập thất bại
- Input sanitization: Laravel form requests
- XSS protection: built-in Blade escaping
- CORS config cho API

---

## TÍCH HỢP BÊN NGOÀI

### TrackingMore API
```php
// app/Services/TrackingMoreService.php
class TrackingMoreService {
    public function detectCourier(string $trackingNumber): string;
    public function getTrackingHistory(string $trackingNumber): array;
    public function syncOrderTracking(Order $order): void;
}
```

### Excel Export
```php
// Dùng Maatwebsite\Excel
// Export job với chunking để tránh timeout
// Giới hạn 10,000 rows per export
```

### QR & Barcode
```php
// Dùng Picqer\Barcode
// Lưu SVG vào storage, link trong DB
```

### Image Upload
```php
// Dùng Intervention Image
// Validate MIME type, kích thước
// Resize + watermark
```

---

## THỨ TỰ IMPLEMENT (SPRINT)

| Sprint | Nội dung | Mục tiêu |
|--------|---------|-----------|
| 1 | Setup project, migrations, models | Laravel chạy được, kết nối DB |
| 2 | Auth + Spatie Permission + Sanctum | Login/logout, phân quyền, API tokens |
| 3 | Order CRUD + Blade views + Status FSM | Tạo/sửa đơn, đổi trạng thái |
| 4 | Order API + Packages + Photos | API endpoints, package management |
| 5 | Pickup + Package Blade pages | Pickup workflow, batch management |
| 6 | CongNo (KH + NCC) | Công nợ 2 hệ thống |
| 7 | Reports + Excel export | Báo cáo 5 format |
| 8 | Master Data + User Management | CRUD reference data |
| 9 | Tracking public API + Public page | Trang public + public REST API |
| 10 | Testing + Security hardening | Unit tests, bug fixes |

---

## CRITICAL FILES

| File | Mô tả |
|------|-------|
| `app/Models/Order.php` | Core — JSON casts, relationships |
| `app/Services/StatusTransitionService.php` | FSM trạng thái |
| `app/Services/WeightCalculationService.php` | Weight logic |
| `app/Providers/AuthServiceProvider.php` | Gates + Policies |
| `config/permission.php` | Spatie config |
| `database/migrations/` | Reverse-engineered từ DB |
| `routes/web.php`, `routes/api.php` | Routes |
| `resources/views/layouts/app.blade.php` | Master layout |
| `.env` | Secrets (APP_KEY, DB credentials) |

---

## VERIFICATION

1. **Unit tests** cho các service phức tạp:
   - `WeightCalculationServiceTest`
   - `StatusTransitionServiceTest`
   - `OrderCodeGeneratorTest`

2. **Feature tests** cho web routes:
   - Tạo đơn → kiểm tra redirect + flash message
   - Update status → kiểm tra transition hợp lệ
   - Export report → kiểm tra file output

3. **Feature tests** cho API endpoints:
   - Tạo đơn → kiểm tra JSON response
   - Update status → kiểm tra transition hợp lệ
   - Public API → kiểm tra rate limiting

4. **Manual testing:**
   - Login các role khác nhau → kiểm tra quyền truy cập menu
   - Tạo đơn → kiểm tra toàn bộ workflow
   - Export invoice → kiểm tra data đúng
   - Public tracking → kiểm tra không cần auth

5. **Security testing:**
   - IDOR test: user A không thể access order của user B
   - SQL injection test: các input form + API
   - Rate limit test: login endpoint, public API
   - CSRF test: form submissions

---

## LƯU Ý QUAN TRỌNG

1. **Giữ nguyên DB prefix `table_`** — config trong `database.php`
2. **`id_customer` → `member.uuid`** — không đổi foreign key
3. **CTV dùng chung bảng `user`** — có thể split sau hoặc dùng type column
4. **`news` table là polymorphic** — dùng cho 13+ loại data
5. **JSON columns cần cast đúng type** — tránh lỗi khi query
6. **Order code `AVN240511001`** — format phải giữ nguyên để integration không break
7. **Dùng Laravelcollective/html** cho form thay thế Form Helper cũ
