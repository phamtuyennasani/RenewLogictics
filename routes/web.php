<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\CongNo\CongNoDataTableController;
use App\Http\Controllers\CongNo\CongNoDaiLyDataTableController;
use App\Http\Controllers\Order\OrderDataTableController;
use App\Services\Providers\Sepay\SepayPaymentService;
use App\Livewire\Dashboard;
use App\Livewire\Order;
use App\Livewire\Login;
use App\Livewire\DuLieu;

if (! app()->environment('production')) {
    Route::get('/dev/sepay-gateway-test', function (Request $request, SepayPaymentService $sepay) {
        $amount = max(1000, (int) $request->integer('amount', 10000));
        $invoiceNumber = (string) $request->query('invoice', 'TESTINV'.now()->format('YmdHis'));
        $description = (string) $request->query('description', 'Test thanh toan sandbox');
        $autoSubmit = $request->boolean('auto_submit', true);

        $data = $sepay->makeGatewayPaymentData($amount, $invoiceNumber, $description, [
            'success_url' => route('dev.sepay-gateway-return', ['status' => 'success']),
            'error_url' => route('dev.sepay-gateway-return', ['status' => 'error']),
            'cancel_url' => route('dev.sepay-gateway-return', ['status' => 'cancel']),
        ]);

        $inputs = collect($data['fields'])
            ->map(fn ($value, $key) => sprintf(
                '<input type="hidden" name="%s" value="%s">',
                e((string) $key),
                e((string) $value),
            ))
            ->implode(PHP_EOL);

        $jsonFields = e(json_encode($data['fields'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $autoSubmitScript = $autoSubmit ? '<script>document.getElementById("sepay-checkout-form").submit();</script>' : '';

        return response(<<<HTML
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SePay Gateway Test</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 32px; color: #111827; }
        code, pre { background: #f3f4f6; border-radius: 8px; padding: 12px; }
        pre { overflow: auto; }
        button { height: 40px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; padding: 0 16px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>
    <h1>SePay Gateway Test</h1>
    <p>Đang chuyển sang cổng thanh toán sandbox. Nếu trình duyệt không tự chuyển, bấm nút bên dưới.</p>
    <p>Checkout endpoint: <code>{$data['checkout_url']}</code></p>
    <form id="sepay-checkout-form" method="POST" action="{$data['checkout_url']}">
        {$inputs}
        <button type="submit">Mở trang thanh toán SePay</button>
    </form>
    <h2>Signed fields</h2>
    <pre>{$jsonFields}</pre>
    {$autoSubmitScript}
</body>
</html>
HTML);
    })->name('dev.sepay-gateway-test');

    Route::get('/dev/sepay-gateway-return/{status}', function (string $status) {
        return response("SePay gateway returned: {$status}");
    })->name('dev.sepay-gateway-return');
}

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
        Route::get('/datatable', OrderDataTableController::class)->name('datatable');
        Route::get('/customers', [OrderDataTableController::class, 'customers'])->name('customers');
        Route::post('/bulk-status', [OrderDataTableController::class, 'bulkStatus'])->name('bulk-status');
        Route::post('/delete-cancelled', [OrderDataTableController::class, 'deleteCancelled'])->name('delete-cancelled');
        Route::get('/export', [OrderDataTableController::class, 'export'])->name('export');
        Route::livewire('/create', 'pages::order.create')->name('create');
        Route::livewire('/{uuid}/payment', 'pages::order.payment')->name('payment');
        Route::livewire('/{uuid}/tracking', 'pages::order.tracking')->name('tracking');
        Route::livewire('/{uuid}', 'pages::order.show')->name('show');
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
        Route::livewire('/', 'pages::congno.index')->name('index');
        Route::get('/datatable', CongNoDataTableController::class)->name('datatable');
        Route::get('/customers', [CongNoDataTableController::class, 'customers'])->name('customers');
        Route::post('/delete-selected', [CongNoDataTableController::class, 'deleteSelected'])->name('delete-selected');
        Route::get('/export', [CongNoDataTableController::class, 'export'])->name('export');
        Route::livewire('/{id}', 'pages::congno.show')->name('show');
    })->middleware('can:congno.index');

    // --- Công nợ Đại lý ---
    Route::prefix('cong-no-dai-ly')->name('congno.daily.')->group(function () {
        Route::livewire('/', 'pages::congnodaily.index')->name('index');
        Route::get('/datatable', CongNoDaiLyDataTableController::class)->name('datatable');
        Route::post('/delete-selected', [CongNoDaiLyDataTableController::class, 'deleteSelected'])->name('delete-selected');
        Route::get('/export', [CongNoDaiLyDataTableController::class, 'export'])->name('export');
        Route::livewire('/{id}', 'pages::congnodaily.show')->name('show');
    })->middleware('can:congno_daily.view');

    // --- Hóa đơn thu ---
    Route::prefix('hoa-don-thu')->name('invoice.')->group(function () {
        Route::livewire('/', 'pages::invoice.index')->name('index');
        Route::get('/datatable', App\Http\Controllers\Invoice\InvoiceDataTableController::class)->name('datatable');
        Route::post('/{id}/approve', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'approve'])->name('approve')->middleware('can:invoice.index');
        Route::post('/{id}/cash', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'submitCashPayment'])->name('cash')->middleware('can:invoice.index');
        Route::post('/{id}/qr', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'submitOnlinePayment'])->name('qr')->middleware('can:invoice.index');
        Route::post('/{id}/regenerate-qr', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'regenerateQr'])->name('regenerate-qr')->middleware('can:invoice.index');
        Route::post('/{id}/confirm-cash', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'confirmCashPayment'])->name('confirm-cash')->middleware('can:invoice.index');
        Route::post('/{id}/reject-cash', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'rejectCashPayment'])->name('reject-cash')->middleware('can:invoice.index');
        Route::post('/{id}/reset-payment-channel', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'resetPaymentChannel'])->name('reset-payment-channel')->middleware('can:invoice.index');
        Route::post('/{id}/cancel', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'cancel'])->name('cancel')->middleware('can:invoice.index');
        Route::get('/sales', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'sales'])->name('sales');
        Route::get('/customers', [App\Http\Controllers\Invoice\InvoiceDataTableController::class, 'customers'])->name('customers');
    })->middleware('can:invoice.index');

    // --- Thống kê ---
    Route::get('/thong-ke', fn () => view('thongke.index'))->name('thongke')
        ->middleware('can:thongke');

    // --- Khách hàng ---
    Route::prefix('khach-hang')->name('customers.')->group(function () {
        Route::get('/', fn () => view('customers.index'))->name('index');
    })->middleware('can:customers.index');

    // --- Địa chỉ gửi ---
    Route::prefix('sender')->name('sender.')->group(function () {
        Route::livewire('/', 'pages::sender.index')->name('index');
        Route::livewire('/add', 'pages::sender.create')->name('add');
        Route::livewire('/edit/{uuid}', 'pages::sender.create')->name('edit');
    })->middleware('can:sender.index');

    // --- Địa chỉ nhận ---
    Route::prefix('receiver')->name('receiver.')->group(function () {
        Route::livewire('/', 'pages::receiver.index')->name('index');
        Route::livewire('/add', 'pages::receiver.create')->name('add');
        Route::livewire('/edit/{uuid}', 'pages::receiver.create')->name('edit');
    })->middleware('can:receiver.index');

    // --- CTV ---
    Route::prefix('ctv')->name('ctv.')->group(function () {
        Route::livewire('/', 'pages::ctv.index')->name('index');
        Route::livewire('/add', 'pages::ctv.create')->name('add');
        Route::livewire('/edit/{id}', 'pages::ctv.create')->name('edit');
    })->middleware('can:ctv.index');

    // --- Nhân sự ---
    Route::prefix('nhan-su')->name('nhansu.')->group(function () {
        Route::livewire('/{type}',         'pages::nhansu.index')->name('index');
        Route::livewire('/{type}/add',     'pages::nhansu.create')->name('add');
        Route::livewire('/{type}/edit/{id}','pages::nhansu.create')->name('edit');
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
        Route::livewire('/{slug}', 'pages::chinhsach.edit')->name('show');
    })->middleware('can:chinhsach.index');

    // --- Cấu hình ---
    Route::prefix('cai-dat')->name('settings.')->group(function () {
        Route::livewire('/', 'pages::settings.index')->name('index');
        Route::livewire('/thong-bao', 'pages::settings.thongbao')->name('thongbao');
        Route::livewire('/logo', 'pages::settings.logo')->name('logo');
        Route::livewire('/favicon', 'pages::settings.favicon')->name('favicon');
        Route::livewire('/banner', 'pages::settings.banner')->name('banner');
        Route::livewire('/social', 'pages::settings.social')->name('social');
        Route::livewire('/thong-tin-cong-ty', 'pages::settings.company')->name('company');
    })->middleware('can:settings.index');

    // --- Profile ---
    Route::livewire('/ho-so', 'pages::taikhoan.index')->name('profile');
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
