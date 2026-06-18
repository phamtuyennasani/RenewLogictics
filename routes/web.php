<?php

use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\VietmapProxyController;
use App\Http\Controllers\CongNo\CongNoDaiLyDataTableController;
use App\Http\Controllers\CongNo\CongNoDataTableController;
use App\Http\Controllers\Invoice\InvoiceDataTableController;
use App\Http\Controllers\Order\OrderDataTableController;
use App\Http\Controllers\Payment\PaymentReturnController;
use App\Livewire\Dashboard;
use App\Livewire\DuLieu;
use App\Livewire\Login;
use App\Services\Providers\Sepay\SepayPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
    Route::livewire('/login', 'pages::auth.login')->name('login');
});
/* ============================================================
   AUTH ROUTES — Đã đăng nhập
   ============================================================ */
Route::middleware('auth')->group(function () {
    // --- Global Search API ---
    Route::get('/api/global-search', GlobalSearchController::class)->name('api.global-search');
    Route::prefix('api/vietmap')->name('api.vietmap.')->middleware('throttle:600,1')->group(function () {
        Route::get('/search', [VietmapProxyController::class, 'search'])->name('search');
        Route::get('/place', [VietmapProxyController::class, 'place'])->name('place');
        Route::get('/reverse', [VietmapProxyController::class, 'reverse'])->name('reverse');
        Route::get('/route', [VietmapProxyController::class, 'routeDirections'])->name('route');
    });

    // --- Dashboard ---
    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard')
        ->middleware('can:dashboard');
    // --- Đơn hàng ---
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::livewire('/', 'pages::order.index')->name('index');
        Route::get('/datatable', OrderDataTableController::class)->name('datatable');
        Route::get('/customers', [OrderDataTableController::class, 'customers'])->name('customers');
        Route::post('/bulk-status', [OrderDataTableController::class, 'bulkStatus'])->name('bulk-status');
        Route::post('/delete-cancelled', [OrderDataTableController::class, 'deleteCancelled'])->name('delete-cancelled');
        Route::get('/export', [OrderDataTableController::class, 'export'])->name('export');
        Route::livewire('/create', 'pages::order.create')->name('create')->middleware('can:orders.create');
        Route::livewire('/{uuid}/payment', 'pages::order.payment')->name('payment');
        Route::livewire('/{uuid}/tracking', 'pages::order.tracking')->name('tracking');
        Route::livewire('/{uuid}', 'pages::order.show')->name('show');
    })->middleware('can:orders.index');
    // --- Pickup ---
    Route::prefix('pickups')->name('pickups.')->group(function () {
        Route::livewire('/', 'pages::pickups.index')->name('index');
        Route::get('/create', fn () => redirect()->route('orders.index'))->name('create');
    })->middleware('can:pickups.index');

    // --- Shipper Mobile Pickup ---
    Route::livewire('/shipper/pickups', 'pages::pickups.shipper')->name('shipper.pickups')
        ->middleware('can:pickups.index');
    Route::livewire('/shipper/scan', 'pages::pickups.shipper-scan')->name('shipper.scan')
        ->middleware('can:pickups.index');
    Route::livewire('/shipper/thong-bao', 'pages::notifications.shipper')->name('shipper.notifications')
        ->middleware('can:notifications.view');
    Route::livewire('/shipper/profile', 'pages::taikhoan.shipper')->name('shipper.profile');

    // --- OPS Web Mobile ---
    Route::prefix('ops/mobile')->name('ops.mobile.')->middleware('role:ops|admin|manager|cs')->group(function () {
        Route::get('/', fn () => redirect()->route('ops.mobile.orders.index'))->name('home');
        Route::livewire('/orders', 'pages::ops-mobile.orders.index')->name('orders.index')
            ->middleware('can:orders.index');
        Route::livewire('/orders/{order}', 'pages::ops-mobile.orders.show')->name('orders.show')
            ->middleware('can:orders.index');
        Route::livewire('/pickups', 'pages::ops-mobile.pickups.index')->name('pickups.index')
            ->middleware('can:pickups.index');
        Route::livewire('/pickups/{pickup}', 'pages::ops-mobile.pickups.show')->name('pickups.show')
            ->middleware('can:pickups.index');
        Route::livewire('/thong-bao', 'pages::notifications.shipper')->name('notifications')
            ->middleware('can:notifications.view');
        Route::livewire('/profile', 'pages::taikhoan.shipper')->name('profile');
    });

    // --- OPS Mobile Scan ---
    Route::livewire('/mobile/scan', 'pages::scan.mobile')->name('mobile.scan')
        ->middleware('can:scan');

    // --- Scan ---
    Route::livewire('/scan', 'pages::scan.index')->name('scan')
        ->middleware(['can:scan', \App\Http\Middleware\RedirectScanToMobile::class]);

    // --- Packages / Tải hàng ---
    Route::prefix('packages')->name('packages.')->middleware('feature:packages')->group(function () {
        Route::livewire('/', 'pages::packages.index')->name('index')->middleware('can:packages.view');
        Route::livewire('/create', 'pages::packages.create')->name('create')->middleware('can:packages.create');
        Route::livewire('/{load}', 'pages::packages.show')->name('show')->middleware('can:packages.view');
    });

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
    Route::prefix('hoa-don-thu')->name('invoice.')->middleware(['feature:invoice', 'can:invoice.index'])->group(function () {
        Route::livewire('/', 'pages::invoice.index')->name('index');
        Route::get('/datatable', InvoiceDataTableController::class)->name('datatable');
        Route::post('/{id}/approve', [InvoiceDataTableController::class, 'approve'])->name('approve')->middleware('can:invoice.index');
        Route::post('/{id}/cash', [InvoiceDataTableController::class, 'submitCashPayment'])->name('cash')->middleware('can:invoice.index');
        Route::post('/{id}/qr', [InvoiceDataTableController::class, 'submitOnlinePayment'])->name('qr')->middleware('can:invoice.index');
        Route::post('/{id}/regenerate-qr', [InvoiceDataTableController::class, 'regenerateQr'])->name('regenerate-qr')->middleware('can:invoice.index');
        Route::post('/{id}/confirm-cash', [InvoiceDataTableController::class, 'confirmCashPayment'])->name('confirm-cash')->middleware('can:invoice.index');
        Route::post('/{id}/reject-cash', [InvoiceDataTableController::class, 'rejectCashPayment'])->name('reject-cash')->middleware('can:invoice.index');
        Route::post('/{id}/reset-payment-channel', [InvoiceDataTableController::class, 'resetPaymentChannel'])->name('reset-payment-channel')->middleware('can:invoice.index');
        Route::post('/{id}/mark-paid', [InvoiceDataTableController::class, 'markPaidByAdmin'])->name('mark-paid')->middleware('can:invoice.index');
        Route::post('/{id}/cancel', [InvoiceDataTableController::class, 'cancel'])->name('cancel')->middleware('can:invoice.index');
        Route::delete('/{id}', [InvoiceDataTableController::class, 'destroy'])->name('destroy')->middleware('can:settings.admin');
        Route::get('/sales', [InvoiceDataTableController::class, 'sales'])->name('sales');
        Route::get('/customers', [InvoiceDataTableController::class, 'customers'])->name('customers');
    });

    // --- Thống kê ---
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
        Route::livewire('/{type}', 'pages::nhansu.index')->name('index');
        Route::livewire('/{type}/add', 'pages::nhansu.create')->name('add');
        Route::livewire('/{type}/edit/{id}', 'pages::nhansu.create')->name('edit');
    })->middleware('can:nhansu.index');

    // --- Dữ liệu ---
    Route::prefix('dich-vu')->name('dichvu.')->group(function () {
        Route::get('/vsvx/mau-excel', function () {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('VSVX');
            $sheet->setCellValue('A1', 'STT');
            $sheet->setCellValue('B1', 'PostCode');

            $samples = ['10000', '084000', '700000', '550000', '900100'];
            foreach ($samples as $i => $code) {
                $row = $i + 2;
                $sheet->setCellValue('A'.$row, $i + 1);
                $sheet->setCellValueExplicit('B'.$row, $code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }

            $sheet->getStyle('B:B')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            $sheet->getStyle('A1:B1')->getFont()->setBold(true);
            $sheet->getColumnDimension('A')->setWidth(10);
            $sheet->getColumnDimension('B')->setWidth(20);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, 'vsvx_mau.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        })->name('vsvx.template');

        Route::livewire('/{type}', 'pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add', 'pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}', 'pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

    Route::prefix('don-vi')->name('donvi.')->group(function () {
        Route::livewire('/{type}', 'pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add', 'pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}', 'pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

    Route::prefix('phan-loai')->name('phanloai.')->group(function () {
        Route::livewire('/{type}', 'pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add', 'pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}', 'pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

    Route::prefix('doi-tac')->name('doitac.')->group(function () {
        Route::livewire('/{type}', 'pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add', 'pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}', 'pages::dulieu.create')->name('edit');
    })->middleware('can:dulieu.index');

    Route::prefix('phu-phi')->name('phuphi.')->group(function () {
        Route::prefix('bang-gia-dich-vu')->name('service-prices.')->group(function () {
            Route::get('/mau-excel', function () {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Bang gia');

                $headers = ['Quy cách', 'Cân nặng từ', 'Cân nặng đến', 'Cước bán', 'Cước vốn', 'Cước gốc'];
                foreach ($headers as $i => $label) {
                    $sheet->setCellValue([$i + 1, 1], $label);
                }

                $samples = [
                    ['CO_DINH', 0, 0.5, 250000, 180000, 150000],
                    ['CO_DINH', 0.5, 1, 350000, 260000, 220000],
                    ['DON_GIA', 0, 1, 450000, 330000, 280000],
                ];
                foreach ($samples as $r => $row) {
                    foreach ($row as $c => $value) {
                        $sheet->setCellValue([$c + 1, $r + 2], $value);
                    }
                }

                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(16);
                }

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

                return response()->streamDownload(function () use ($writer) {
                    $writer->save('php://output');
                }, 'bang_gia_dich_vu_mau.xlsx', [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            })->name('template');

            Route::livewire('/', 'pages::service-prices.index')->name('index');
            Route::livewire('/add', 'pages::service-prices.form')->name('add');
            Route::livewire('/edit/{id}', 'pages::service-prices.form')->name('edit');
        });
        Route::livewire('/{type}', 'pages::dulieu.index')->name('index');
        Route::livewire('/{type}/add', 'pages::dulieu.create')->name('add');
        Route::livewire('/{type}/edit/{id}', 'pages::dulieu.create')->name('edit');
    })->middleware('can:phuphi.index');

    Route::prefix('place')->name('place.')->group(function () {
        Route::livewire('/{type}', 'pages::place.index')->name('index');
        Route::livewire('/{type}/add', 'pages::place.create')->name('add');
        Route::livewire('/{type}/edit/{id}', 'pages::place.create')->name('edit');
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

    Route::livewire('/cai-dat/thong-bao', 'pages::settings.thongbao')
        ->name('settings.thongbao')
        ->middleware('can:notifications.view');

    // --- Cấu hình ---
    Route::prefix('cai-dat')->name('settings.')->group(function () {
        Route::livewire('/', 'pages::settings.index')->name('index')->middleware('can:settings.admin');
        Route::livewire('/logo', 'pages::settings.logo')->name('logo')->middleware('can:settings.admin');
        Route::livewire('/favicon', 'pages::settings.favicon')->name('favicon')->middleware('can:settings.admin');
        Route::livewire('/banner', 'pages::settings.banner')->name('banner')->middleware('can:settings.admin');
        Route::livewire('/social', 'pages::settings.social')->name('social')->middleware('can:settings.admin');
        Route::livewire('/thong-tin-cong-ty', 'pages::settings.company')->name('company')->middleware('can:settings.admin');
        Route::livewire('/he-thong', 'pages::settings.he-thong')->name('he-thong')->middleware('can:settings.admin');
    })->middleware('can:settings.index');

    // --- Profile ---
    Route::livewire('/ho-so', 'pages::taikhoan.index')->name('profile');

    // --- Nhật ký hệ thống (audit log) — chỉ admin ---
    Route::livewire('/nhat-ky-he-thong', 'pages::activity-log.index')->name('activity-log.index')
        ->middleware('can:activity-log.view');

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
Route::get('/thanh-toan/{provider}/return', PaymentReturnController::class)
    ->whereIn('provider', ['vnpay', 'momo'])
    ->name('payment.return')
    ->middleware('throttle:30,1');

Route::get('/theo-doi/{idbill}', fn ($idbill) => view('tracking.index', ['idbill' => $idbill]))
    ->name('tracking')
    ->middleware('throttle:10,1');

/* ============================================================
   HOME — Redirect theo trạng thái login
   ============================================================ */
Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }

    // Shipper luôn vào giao diện mobile pickup
    if (Auth::user()->hasRole('shipper')) {
        return redirect()->route('shipper.pickups');
    }

    return redirect()->route('dashboard');
})->name('home');
