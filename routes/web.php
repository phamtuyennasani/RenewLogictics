<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Dashboard;
use App\Livewire\Order;
use App\Livewire\Login;
use App\Livewire\DuLieu;
/* ============================================================
   GUEST ROUTES — Chưa đăng nhập
   ============================================================ */
Route::middleware('guest')->group(function () {
    Route::livewire('/login','pages::auth.login')->name('login');
});
/* ============================================================
   AUTH ROUTES — Đã đăng nhập
   ============================================================ */
Route::middleware('auth')->group(function () {
    // --- Dashboard ---
    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');
    // --- Đơn hàng ---
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::livewire('/', 'pages::order.index')->name('index');
        Route::livewire('/create', 'pages::order.create')->name('create');
        Route::livewire('/{id}', 'pages::order.show')->name('show');
    })->middleware('can:orders.index');
    // --- Pickup ---
    Route::prefix('pickups')->name('pickups.')->group(function () {
        Route::get('/', fn () => view('pickups.index'))->name('index');
        Route::get('/create', fn () => view('pickups.create'))->name('create');
    })->middleware('can:pickups.index');

    // --- Scan ---
    Route::get('/scan', fn () => view('scan.index'))->name('scan')
        ->middleware('can:scan');

    // --- Packages / Tải hàng ---
    Route::prefix('packages')->name('packages.')->group(function () {
        Route::get('/', fn () => view('packages.index'))->name('index');
    })->middleware('can:packages.index');

    // --- Công nợ CTV ---
    Route::prefix('cong-no')->name('congno.')->group(function () {
        Route::get('/', fn () => view('congno.index'))->name('index');
        Route::get('/{id}', fn ($id) => view('congno.show', ['id' => $id]))->name('show');
    })->middleware('can:congno.index');

    // --- Công nợ Đại lý ---
    Route::prefix('cong-no-dai-ly')->name('congno.daily')->group(function () {
        Route::get('/', fn () => view('congno.daily'))->name('index');
    })->middleware('can:congno.daily');

    // --- Thống kê ---
    Route::get('/thong-ke', fn () => view('thongke.index'))->name('thongke')
        ->middleware('can:thongke');

    // --- Khách hàng ---
    Route::prefix('khach-hang')->name('customers.')->group(function () {
        Route::get('/', fn () => view('customers.index'))->name('index');
    })->middleware('can:customers.index');

    // --- Địa chỉ nhận ---
    Route::prefix('dia-chi')->name('addresses.')->group(function () {
        Route::get('/', fn () => view('addresses.index'))->name('index');
    })->middleware('can:addresses.index');

    // --- CTV ---
    Route::prefix('ctv')->name('ctv.')->group(function () {
        Route::get('/', fn () => view('ctv.index'))->name('index');
    })->middleware('can:ctv.index');

    // --- Nhân sự ---
    Route::prefix('nhan-su')->name('nhansu.')->group(function () {
        Route::get('/sale', fn () => view('nhansu.sale'))->name('sale');
        Route::get('/internal', fn () => view('nhansu.internal'))->name('internal');
        Route::get('/internal/ketoan', fn () => view('nhansu.ketoan'))->name('ketoan');
        Route::get('/internal/cs', fn () => view('nhansu.cs'))->name('cs');
        Route::get('/internal/ops', fn () => view('nhansu.ops'))->name('ops');
        Route::get('/internal/shipper', fn () => view('nhansu.shipper'))->name('shipper');
        Route::get('/manager', fn () => view('nhansu.manager'))->name('manager');
    })->middleware('can:nhansu.index');

    // --- Dữ liệu ---
    Route::prefix('dich-vu')->name('dichvu.')->group(function () {
        Route::livewire('/{type}','pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add','pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}','pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

    Route::prefix('don-vi')->name('donvi.')->group(function () {
        Route::livewire('/{type}','pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add','pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}','pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

    Route::prefix('phan-loai')->name('phanloai.')->group(function () {
        Route::livewire('/{type}','pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add','pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}','pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

    Route::prefix('doi-tac')->name('doitac.')->group(function () {
        Route::livewire('/{type}','pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add','pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}','pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

    Route::prefix('phu-phi')->name('phuphi.')->group(function () {
        Route::livewire('/{type}','pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add','pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}','pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

   
    Route::prefix('place')->name('place.')->group(function () {
        Route::livewire('/{type}','pages::place.index')->name('index');
        Route::livewire('/{type}/add','pages::place.create')->name('add');
        Route::livewire('/{type}/edit/{id}','pages::place.create')->name('edit');
    })->middleware('can:dulieu.index');
  

    // ================================================================
    // DULIEU — Mỗi view là 1 Livewire component cùng cấu trúc
    // Chỉ khác: title, type (filter news), columns, formFields
    // ================================================================

    // --- Chính sách ---
    Route::prefix('chinh-sach')->name('chinhsach.')->group(function () {
        Route::get('/', fn () => view('chinhsach.index'))->name('index');
        Route::get('/quy-dinh-tao-don', fn () => view('chinhsach.quydinh-taodon'))->name('quydinh-taodon');
        Route::get('/quy-dinh-khai-hang', fn () => view('chinhsach.quydinh-khahang'))->name('quydinh-khahang');
        Route::get('/quy-dinh-them-tai', fn () => view('chinhsach.quydinh-themtai'))->name('quydinh-themtai');
        Route::get('/chinh-sach-dieu-khoan', fn () => view('chinhsach.dieukhoan'))->name('dieukhoan');
        Route::get('/bao-mat-thong-tin', fn () => view('chinhsach.baomat'))->name('baomat');
    })->middleware('can:chinhsach.index');

    // --- Cấu hình ---
    Route::prefix('cai-dat')->name('settings.')->group(function () {
        Route::get('/', fn () => view('settings.index'))->name('index');
        Route::get('/thong-bao', fn () => view('settings.thongbao'))->name('thongbao');
        Route::get('/logo', fn () => view('settings.logo'))->name('logo');
        Route::get('/favicon', fn () => view('settings.favicon'))->name('favicon');
        Route::get('/banner', fn () => view('settings.banner'))->name('banner');
        Route::get('/social', fn () => view('settings.social'))->name('social');
        Route::get('/thong-tin-cong-ty', fn () => view('settings.company'))->name('company');
    })->middleware('can:settings.index');

    // --- Profile ---
    Route::get('/ho-so', fn () => view('profile.index'))->name('profile')
        ->middleware('can:profile');
    // --- Logout ---
    Route::get('/logout', function () {
        \Auth::logout();
        \Session::invalidate();
        \Session::regenerateToken();
        return redirect()->route('login');
    })->name('logout');
});

/* ============================================================
   PUBLIC ROUTES — Không cần đăng nhập
   ============================================================ */
Route::get('/theo-doi/{idbill}', fn ($idbill) => view('tracking.index', ['idbill' => $idbill]))
    ->name('tracking')
    ->middleware('throttle:10,1');

/* ============================================================
   HOME — Redirect theo trạng thái login
   ============================================================ */
Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'))->name('home');
