<?php

/**
 * Seed demo accounts:
 * - 5 sale, 2 manager, 3 cs, 5 ops, 5 shipper
 * - 10 customers (CTV) chia đều cho 5 sale (mỗi sale 2 khách)
 * - 10 receivers (Member type=receiver) chia đều cho 10 khách (mỗi khách 1 receiver)
 *
 * Mật khẩu mặc định: 123456789
 *
 * Chạy: php database/seed-demo-accounts.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Member;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

$password = bcrypt('123456789');

// Đảm bảo mọi role tồn tại (idempotent).
foreach (['admin','manager','ketoan','cs','sale','ops','ctv','shipper'] as $r) {
    Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
}

/**
 * Tạo nhanh 1 user theo role + prefix code.
 */
$createUser = function (string $role, string $prefix, int $index, ?int $idSale = null, array $location = []) use ($password): User {
    $username = strtolower($prefix).sprintf('%02d', $index);
    $code = strtoupper($prefix).sprintf('%02d', $index);
    $fullname = match ($role) {
        'sale'    => 'Sale '.$index,
        'manager' => 'Manager '.$index,
        'ketoan'  => 'Kế toán '.$index,
        'cs'      => 'CS '.$index,
        'ops'     => 'Ops '.$index,
        'shipper' => 'Shipper '.$index,
        'ctv'     => 'Khách hàng '.$index,
        default   => ucfirst($role).' '.$index,
    };

    $user = User::withTrashed()->where('username', $username)->first();

    $payload = [
        'username' => $username,
        'code'     => $code,
        'fullname' => $fullname,
        'email'    => $username.'@example.com',
        'phone'    => '0900'.str_pad((string) (1000 + $index + crc32($role) % 9000), 6, '0', STR_PAD_LEFT),
        'password' => $password,
        'status'   => '1',
        'id_sale'  => $idSale,
    ];

    if ($role === 'ctv') {
        $payload['options'] = [
            'company' => [
                'company_name' => 'Công ty khách hàng '.$index,
                'company_short_name' => 'KH'.$index,
                'representative_name' => $fullname,
                'tax_code' => '01000'.str_pad((string) (1000 + $index), 6, '0', STR_PAD_LEFT),
                'company_email' => $username.'@example.com',
                'company_phone' => $payload['phone'],
                'city_id' => $location['city_id'] ?? null,
                'ward_id' => $location['ward_id'] ?? null,
                'address_detail' => 'Số '.$index.' Đường Demo',
            ],
            'dim' => 5000,
        ];
    }

    if ($user) {
        if ($user->trashed()) {
            $user->restore();
        }
        $user->update(array_filter($payload, fn ($v, $k) => $v !== null || $k === 'id_sale', ARRAY_FILTER_USE_BOTH));
    } else {
        $user = User::create($payload);
    }

    $user->syncRoles([$role]);

    return $user;
};

$report = ['sale'=>[], 'manager'=>[], 'ketoan'=>[], 'cs'=>[], 'ops'=>[], 'shipper'=>[], 'ctv'=>[], 'receiver'=>[]];

// Chọn sẵn 10 cặp (city_id, ward_id) hợp lệ để gán cho khách hàng.
$locationPairs = [];
$provinceIds = \Illuminate\Support\Facades\DB::table('province')->orderBy('id')->pluck('id')->all();
foreach ($provinceIds as $pid) {
    $ward = \Illuminate\Support\Facades\DB::table('wards')->where('parent_code', $pid)->orderBy('id')->first();
    if ($ward) {
        $locationPairs[] = ['city_id' => $pid, 'ward_id' => $ward->id];
    }
    if (count($locationPairs) >= 10) {
        break;
    }
}
if ($locationPairs === []) {
    throw new \RuntimeException('Không tìm thấy province/ward hợp lệ để gán cho khách hàng.');
}

// 1) 5 sale
$sales = [];
for ($i = 1; $i <= 5; $i++) {
    $sales[] = $createUser('sale', 'sale', $i);
}
$report['sale'] = collect($sales)->pluck('username')->all();

// 2) 2 manager
$managers = [];
for ($i = 1; $i <= 2; $i++) {
    $managers[] = $createUser('manager', 'manager', $i);
}
$report['manager'] = collect($managers)->pluck('username')->all();

// 3) 3 cs
$csList = [];
for ($i = 1; $i <= 3; $i++) {
    $csList[] = $createUser('cs', 'cs', $i);
}
$report['cs'] = collect($csList)->pluck('username')->all();

// 3b) 10 ketoan
$ketoanList = [];
for ($i = 1; $i <= 10; $i++) {
    $ketoanList[] = $createUser('ketoan', 'ketoan', $i);
}
$report['ketoan'] = collect($ketoanList)->pluck('username')->all();

// 4) 5 ops
$opsList = [];
for ($i = 1; $i <= 5; $i++) {
    $opsList[] = $createUser('ops', 'ops', $i);
}
$report['ops'] = collect($opsList)->pluck('username')->all();

// 5) 5 shipper
$shippers = [];
for ($i = 1; $i <= 5; $i++) {
    $shippers[] = $createUser('shipper', 'shipper', $i);
}
$report['shipper'] = collect($shippers)->pluck('username')->all();

// 6) 10 khách hàng (ctv) chia đều cho 5 sale → 2/sale
$ctvList = [];
for ($i = 1; $i <= 10; $i++) {
    $sale = $sales[($i - 1) % 5];
    $location = $locationPairs[($i - 1) % count($locationPairs)];
    $ctvList[] = $createUser('ctv', 'kh', $i, $sale->id, $location);
}
$report['ctv'] = collect($ctvList)->map(fn (User $u) => $u->username.' (sale='.$u->id_sale.', city='.data_get($u->options, 'company.city_id').', ward='.data_get($u->options, 'company.ward_id').')')->all();

// 7) 10 receiver — mỗi khách 1 receiver
$receivers = [];
for ($i = 1; $i <= 10; $i++) {
    $ctv = $ctvList[$i - 1];

    $code = 'RECV'.sprintf('%04d', $i);
    $member = Member::where('type', 'receiver')->where('code', $code)->first();

    $payload = [
        'company_name' => 'Cty Nhận '.$i,
        'fullname' => 'Người Nhận '.$i,
        'email' => 'recv'.$i.'@example.com',
        'phone' => '0911'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
        'code' => $code,
        'id_sale' => $ctv->id_sale,
        'id_ctv' => $ctv->id,
        'country_id' => 233, // United States
        'state' => 'California',
        'cities' => 'Los Angeles',
        'postcode' => '900'.sprintf('%02d', $i),
        'address' => '1'.$i.'00 Demo Street',
        'type' => 'receiver',
    ];

    if ($member) {
        $member->update($payload);
    } else {
        $payload['uuid'] = (string) Str::uuid();
        $member = Member::create($payload);
    }

    $receivers[] = $member;
}
$report['receiver'] = collect($receivers)->map(fn (Member $m) => $m->code.' → CTV id_ctv='.$m->id_ctv.', sale='.$m->id_sale)->all();

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
