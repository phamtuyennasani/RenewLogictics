# Payment Invoice Review Notes

Ngay kiem tra: 2026-05-25

Trang thai tong quan: chua nen xem la hoan tat de merge/commit. UI va cau truc chinh da co, nhung con mot so diem logic nghiep vu can xu ly truoc khi go live.

## Diem can chu y

### 1. Backfill payment cu co nguy co sai trang thai

Muc do: High

File lien quan:
- `database/migrations/2026_05_25_000001_extend_payments_for_invoices.php`
- `app/Models/CongNo.php`
- `app/Models/CongNoDaiLy.php`

Van de:
- Migration them cot `status` voi default `moi_tao`.
- Sau do backfill chi update cac dong `status IS NULL` hoac rong thanh `da_thanh_toan`.
- Neu database co payment cu, cac dong cu co the bi gan san `moi_tao`, khong duoc backfill thanh `da_thanh_toan`.
- `syncPaidAmountFromPayments()` hien chi sum invoice co status `da_thanh_toan`, nen `paid_amount` co the sai.

Can xu ly:
- Xac minh tren database co du lieu cu hay khong.
- Neu co, can sua migration/backfill de cac payment cu duoc danh dau `da_thanh_toan` dung.
- Can co script/fix rieng neu migration da chay tren moi truong co data.

### 2. Webhook dang auto-match ca invoice chi moi duyet

Muc do: High

File lien quan:
- `app/Services/Payments/PaymentInvoiceMatcher.php`
- `app/Models/CongNoPayment.php`

Van de:
- Plan yeu cau webhook chi auto-confirm invoice o trang thai `DA_GUI_YEU_CAU_TT`.
- Code hien tai match ca `DA_GUI_YEU_CAU_TT` va `DA_DUYET`.
- `qr_payment_code` lai duoc sinh ngay khi tao invoice, nen invoice moi duyet nhung chua tao QR online van co the bi webhook danh dau da thanh toan neu noi dung chuyen khoan trung ma.

Can xu ly:
- Chi match webhook voi status `DA_GUI_YEU_CAU_TT`.
- Can can nhac chi sinh `qr_payment_code` khi tao yeu cau thanh toan online, thay vi sinh ngay luc create invoice.

### 3. Webhook online chua dong bo trang thai order

Muc do: Medium

File lien quan:
- `app/Services/Payments/PaymentInvoiceMatcher.php`
- `resources/views/pages/congno/⚡show/show.php`

Van de:
- Cash confirm co update `orders.customer_payment_status` va `customer_paid_at`.
- Webhook online chi update invoice + goi `syncPaidAmountFromPayments()`.
- Ket qua: debt co the da thanh toan/mot phan, nhung order lien quan chua duoc dong bo trang thai thanh toan.

Can xu ly:
- Sau khi webhook danh dau invoice `DA_THANH_TOAN`, can update order status giong logic `confirmCashPayment()`.
- Nen dua logic dong bo order thanh method dung chung de tranh lech giua cash va online.

### 4. Luong hoa don chi chua dong bo trang thai order dai ly

Muc do: Medium

File lien quan:
- `resources/views/pages/congnodaily/⚡show.blade.php`
- `app/Models/CongNoDaiLy.php`

Van de:
- Khi mark paid hoa don chi, code sync `paid_amount` cua debt.
- Chua thay update `orders.agency_payment_status` va `agency_paid_at`.
- Trong khi luc tao cong no dai ly co set `agency_payment_status`, va khi huy debt co reset cac field nay.

Can xu ly:
- Khi HĐ chi duoc mark paid, can dong bo `agency_payment_status` theo trang thai debt: thanh toan mot phan hoac da thanh toan.
- Khi debt da thanh toan het, set `agency_paid_at`.

### 5. Section hoa don co the van hien khi cong no chua chot neu da co invoice

Muc do: Low / Plan mismatch

File lien quan:
- `resources/views/pages/congno/⚡show/show.blade.php`

Van de:
- Plan ghi an section hoa don khi cong no chua chot.
- View customer hien section neu `$canCreateInvoice || $sortedInvoices->isNotEmpty()`.
- Neu co invoice ton tai tren cong no chua chot, section van hien.

Can xu ly:
- Xac nhan yeu cau nghiep vu: co muon cho xem invoice cu khi cong no chua chot khong.
- Neu can bam sat plan, chi hien section khi debt duoc phep tao invoice hoac debt da qua trang thai chot.

## Cac phan da dat yeu cau

- Danh sach order trong trang chi tiet cong no khach hang da co cac cot: ma order, nguoi gui, nguoi nhan, quoc gia, dich vu, trang thai bill, cuoc ban.
- Da co nut edit cuoc ban va nut delete order.
- Modal edit cuoc ban da mo rong `max-w-8xl`.
- Modal edit cuoc ban hien day du cac nhom du lieu can thiet tu payment: cuoc ban, VAT, PPXD, phu phi ban, hoa hong khach hang.
- Khi luu cuoc ban, code co tinh lai payment cua order, hoa hong sale, loi nhuan, detail cong no va tong cong no.
- PHP syntax check da pass cho cac file lien quan.
- `php artisan view:cache` da pass.

## Verification can lam truoc commit

1. Tao HĐ Thu, duyet, thanh toan cash, ke toan confirm.
2. Tao HĐ Thu, duyet, tao QR online, gui webhook gia lap dung ma va dung amount.
3. Kiem tra sau webhook: invoice, debt va order deu dong bo trang thai.
4. Tao HĐ Chi, mark paid, kiem tra debt va order dai ly dong bo trang thai.
5. Test constraint: tong invoice pending + paid khong vuot tong cong no.
6. Test huy invoice: amount available duoc tra lai dung.
7. Neu co data payment cu, kiem tra backfill status va `paid_amount`.
8. Chay lai GitNexus `detect_changes` truoc commit de xac nhan pham vi thay doi dung du kien.

---

## Review bo sung sau khi ky thuat fix plan

Ngay kiem tra: 2026-05-26

Trang thai: cac fix #2, #3, #4, #5 trong `PAYMENT_INVOICE_PLAN.md` da co trong code. Tuy nhien van con mot so diem can bo sung/fix truoc khi xem la hoan tat.

### 6. Webhook can reject payload thieu amount hoac amount <= 0

Muc do: High

File lien quan:
- `app/Services/Payments/PaymentInvoiceMatcher.php`

Van de:
- Code hien lay amount bang `(int) ($payload['transferAmount'] ?? 0)`.
- Check mismatch chi chay khi `$amount > 0`.
- Neu webhook payload thieu `transferAmount` hoac amount bang 0, code co the bo qua check amount va van set invoice sang `DA_THANH_TOAN`.
- Dieu nay chua dung voi plan: webhook chi duoc auto-confirm khi amount khop invoice voi tolerance cho phep.

Can xu ly:
- Reject ngay neu `transferAmount` khong ton tai hoac amount <= 0.
- Sau do so sanh amount voi expected invoice amount. Neu lech qua tolerance thi return null.
- Nen log warning rieng cho case invalid/missing amount.

Goi y logic:

```php
$amount = (int) ($payload['transferAmount'] ?? 0);

if ($amount <= 0) {
    Log::warning('SePay matcher: invalid amount', [
        'code' => $code,
        'amount' => $payload['transferAmount'] ?? null,
    ]);

    return null;
}

if ($expected > 0 && abs($amount - $expected) > 1) {
    // reject mismatch
}
```

### 7. Nut tao lai QR khong hien va backend regenerate QR chua check quyen chat che

Muc do: Medium

File lien quan:
- `app/Models/CongNoPayment.php`
- `resources/views/pages/congno/⚡show/show.php`
- `resources/views/pages/congno/⚡show/show.blade.php`

Van de:
- `canPay()` chi true khi invoice status la `DA_DUYET`.
- View lai hien nut "Tao lai QR" khi status la `DA_GUI_YEU_CAU_TT` va `$invoice->canPay($authUser)`.
- Hai dieu kien nay mau thuan, nen nut "Tao lai QR" khong hien.
- Method `regenerateQr()` cho phep qua neu status la `DA_GUI_YEU_CAU_TT`, nhung khong check user la creator/admin/manager/ketoan trong nhanh nay.

Can xu ly:
- Tao helper quyen rieng, vi du `canManageQr(?User $user): bool`.
- Helper nay nen cho creator hoac staff power thao tac khi invoice dang `DA_GUI_YEU_CAU_TT`.
- Dung cung helper trong UI va trong `regenerateQr()`.
- Sau do UI hien nut khi `status=DA_GUI_YEU_CAU_TT`, user co quyen, va throttle cho phep.

Goi y:

```php
public function canManageQr(?User $user): bool
{
    return $this->status === InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT
        && ($this->isCreator($user) || $this->hasStaffPower($user));
}
```

### 8. Backfill payment cu chi an toan neu moi truong deploy khong co data cu

Muc do: Medium / Environment risk

File lien quan:
- `database/migrations/2026_05_25_000001_extend_payments_for_invoices.php`

Trang thai kiem tra:
- Local DB hien tai: `congno_payments = 0`, `congno_daily_payments = 0`.
- Vi vay fix theo plan la chap nhan duoc tren DB local hien tai.

Van de con lai:
- Migration van them `status` default `moi_tao`, sau do backfill chi update rows `status IS NULL` hoac rong.
- Neu deploy len moi truong co payment cu, rui ro backfill sai van ton tai.

Can xu ly:
- Xac nhan tat ca moi truong deploy co 2 bang payment empty truoc khi migrate.
- Neu co moi truong co payment cu, can viet migration/fix data rieng de mark payment cu thanh `da_thanh_toan`.

### 9. Thieu automated test cho luong Payment Invoice

Muc do: Medium

File lien quan:
- `tests/Feature` hoac `tests/Unit`

Van de:
- `php artisan test --filter=PaymentInvoice` khong tim thay test.
- Luong nay cham payment, webhook, cong no, order status; manual test la chua du.

Can bo sung test uu tien:
- `PaymentInvoiceMatcher` khong match khi status != `DA_GUI_YEU_CAU_TT`.
- `PaymentInvoiceMatcher` khong match khi amount thieu/0/lech amount.
- `PaymentInvoiceMatcher` match thanh cong thi update invoice, debt, `orders.customer_payment_status`.
- HĐ Chi mark paid thi update debt va `orders.agency_payment_status`.
- Constraint tao invoice khong vuot qua `availableForNewInvoice()`.

### 10. Ket qua test hien tai

- `php -l` cac file lien quan: pass.
- `php artisan view:cache`: pass.
- `php artisan route:list --path=webhooks`: route SePay webhook ton tai.
- `php artisan test --filter=PaymentInvoice`: khong co test.
- `php artisan test`: 17 pass, 1 fail o `Tests\Feature\ExampleTest` vi `/` tra `302` thay vi `200`. Loi nay co ve khong lien quan truc tiep den Payment Invoice, nhung can ghi nhan truoc commit.
