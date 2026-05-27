# Các điểm cần cải thiện: Hóa đơn cho đơn hàng vãng lai

> Ngày rà soát: 2026-05-27  
> Phạm vi: Đối chiếu `docs/TEST_PLAN__HoaDonDonHangVangLai.md` với code hiện tại.

## Tổng quan

Logic hiện tại đã có phần lớn luồng chính: tạo hóa đơn trực tiếp từ đơn hàng, auto duyệt, khóa cước bán, thanh toán tiền mặt, QR online, danh sách hóa đơn và lịch sử trên chi tiết đơn hàng.

Tuy nhiên vẫn còn một số điểm lệch so với test plan hoặc có nguy cơ sai nghiệp vụ. Các mục dưới đây nên được kỹ thuật kiểm tra và chốt hướng xử lý trước khi nghiệm thu.

## Mức độ cao

### 1. Chưa chặn tạo hóa đơn cho đơn không phải vãng lai

- File liên quan:
  - `app/Services/OrderInvoiceService.php`
  - `app/Models/Order.php`
- Hiện trạng:
  - `OrderInvoiceService::createForOrder()` và `canCreateInvoice()` chưa kiểm tra `$order->isWalkIn()`.
  - Điều này có thể cho phép tạo hóa đơn trực tiếp cho đơn có `id_customer` khác `0/null`.
- Mong đợi theo test plan:
  - Chỉ đơn vãng lai (`id_customer = 0|null`) mới được tạo hóa đơn trực tiếp.
- Đề xuất:
  - Thêm guard ở service:
    - Nếu không phải đơn vãng lai, trả lỗi nghiệp vụ rõ ràng.
  - Cập nhật UI để không hiển thị nút tạo hóa đơn cho đơn không vãng lai.

### 2. Webhook thanh toán online chưa sync trạng thái đơn hàng vãng lai

- File liên quan:
  - `app/Services/Payments/PaymentInvoiceMatcher.php`
  - `app/Services/OrderInvoiceService.php`
- Hiện trạng:
  - Webhook tìm invoice và chuyển invoice sang `DA_THANH_TOAN`.
  - Sau đó chỉ sync qua `$invoice->congNo`.
  - Với hóa đơn đơn lẻ, `id_congno = null`, nên đơn hàng không được cập nhật `customer_payment_status` và `customer_paid_at`.
- Mong đợi theo test plan:
  - Khi webhook thanh toán thành công: invoice `DA_THANH_TOAN`, order `customer_payment_status = DA_THANH_TOAN`, `customer_paid_at = now()`.
- Đề xuất:
  - Trong matcher, nếu `$invoice->hasDirectOrder()` thì lock order và cập nhật trạng thái thanh toán.
  - Có thể gọi chung `OrderInvoiceService::syncOrderPaymentStatus()` sau khi invoice paid.

### 3. Không thể hủy QR online trong 15 phút đầu

- File liên quan:
  - `app/Models/CongNoPayment.php`
- Hiện trạng:
  - `canCancel()` đang chặn hủy invoice trạng thái `DA_GUI_YEU_CAU_TT` nếu chưa đủ điều kiện `canRegenerateQr()`.
  - Nghĩa là QR vừa tạo xong thì không hủy được trong 15 phút.
- Mong đợi theo test plan:
  - `DA_GUI_YEU_CAU_TT -> HUY` được phép trước khi thanh toán.
- Đề xuất:
  - Tách điều kiện hủy khỏi điều kiện regenerate QR.
  - Chỉ chặn hủy với trạng thái cuối: `DA_THANH_TOAN`, `HUY`.

## Mức độ trung bình

### 4. Sau khi hủy, trang payment không còn hiển thị lý do hủy

- File liên quan:
  - `app/Models/Order.php`
  - `resources/views/pages/order/⚡payment.blade.php`
  - `resources/views/components/order/⚡payment-invoices.blade.php`
- Hiện trạng:
  - `Order::getActiveInvoice()` loại invoice `HUY`.
  - Sau khi hủy, `loadInvoice()` sẽ set `$this->invoice = null`.
  - Section payment có thể hiển thị trạng thái chưa có invoice, thay vì hiển thị invoice đã hủy và lý do hủy.
- Mong đợi theo test plan:
  - Lý do hủy hiển thị trên trang payment và trang chi tiết.
- Đề xuất:
  - Chốt UX:
    - Trang payment hiển thị invoice gần nhất kể cả `HUY`, đồng thời vẫn cho tạo invoice mới; hoặc
    - Giữ payment chỉ hiển thị active invoice, nhưng thêm block lịch sử invoice ngay trên payment.
  - Trang chi tiết hiện đã có component lịch sử, cần xác minh hiển thị lý do hủy đúng.

### 5. Gate route danh sách hóa đơn không cho sale truy cập

- File liên quan:
  - `app/Providers/AuthServiceProvider.php`
  - `routes/web.php`
- Hiện trạng:
  - Gate `invoice.index` chỉ cho `admin`, `ketoan`, `manager`.
  - Test plan có nhiều bước yêu cầu sale/user tạo hóa đơn thao tác lại chứng từ; các API `/hoa-don-thu/...` cũng dùng middleware `can:invoice.index`.
- Điểm cần chốt:
  - Nếu sale cần thao tác từ danh sách hóa đơn/API chung, gate phải cho sale vào và query phải lọc theo sale.
  - Nếu sale chỉ thao tác trong trang order payment thì test plan nên ghi rõ.
- Đề xuất:
  - Chốt lại ma trận quyền thực tế.
  - Nếu sale được phép vào hóa đơn thu, thêm `sale` vào gate `invoice.index` và giữ query filter theo sale.

### 6. Tạo lại QR không sinh payment code mới

- File liên quan:
  - `app/Http/Controllers/Invoice/InvoiceDataTableController.php`
  - `resources/views/pages/order/⚡payment.blade.php`
- Hiện trạng:
  - Khi regenerate QR, code đang ưu tiên dùng lại `payment_reference` hoặc `qr_payment_code` cũ.
- Mong đợi theo test plan:
  - QR mới được tạo với payment code mới.
- Điểm cần chốt:
  - Nếu muốn giữ cùng mã tham chiếu để đối soát dễ hơn, test plan cần sửa.
  - Nếu bắt buộc code mới, cần bỏ ưu tiên reuse code khi regenerate.
- Đề xuất:
  - Chốt rule nghiệp vụ trước khi sửa.

## Lệch giữa test plan và code

### 7. URL danh sách hóa đơn trong test plan sai so với route hiện tại

- Test plan ghi:
  - `/invoices`
- Route hiện tại:
  - `/hoa-don-thu`
- File liên quan:
  - `routes/web.php`
- Đề xuất:
  - Sửa test plan sang `/hoa-don-thu`, hoặc thêm alias route `/invoices` nếu muốn giữ URL tiếng Anh.

### 8. Format mã hóa đơn lệch

- Test plan mong:
  - `INV-YYYYMMDD-XXXX`
- Code hiện tại:
  - `HD-TH-YYYYMMDD-XXXX` cho hóa đơn thu.
- File liên quan:
  - `app/Enums/InvoiceTypeEnum.php`
  - `app/Services/Payments/InvoiceCodeGenerator.php`
- Đề xuất:
  - Chốt format chính thức.
  - Nếu giữ `HD-TH`, cập nhật test plan.
  - Nếu cần `INV`, đổi `InvoiceTypeEnum::codePrefix()` và kiểm tra ảnh hưởng với webhook/payment code.

### 9. Test plan nói nút "Lưu cước bán", UI hiện là "Lưu giá"

- File liên quan:
  - `resources/views/pages/order/⚡payment.blade.php`
- Hiện trạng:
  - UI dùng nút `Lưu giá`, không phải `Lưu cước bán`.
- Đề xuất:
  - Sửa test plan theo label thực tế hoặc đổi label UI nếu nghiệp vụ muốn rõ hơn.

## Các điểm nên kiểm tra thêm

### 10. Nút lưu vẫn hiển thị khi cước bán bị khóa

- File liên quan:
  - `resources/views/pages/order/⚡payment.blade.php`
- Hiện trạng:
  - Khi có invoice active, `canEditSaleCharge()` trả `false`, form cước bán readonly.
  - Tuy nhiên nút `Lưu giá` vẫn hiển thị vì trang còn cho lưu cước vốn/cước gốc theo quyền.
- Mong đợi theo test plan:
  - Không hiển thị nút "Lưu cước bán".
- Đề xuất:
  - Nếu chỉ cần khóa cước bán, giữ như hiện tại và sửa test plan.
  - Nếu muốn không cho lưu toàn bộ giá khi có invoice, cần thêm guard ở UI và backend.

### 11. `Order::hasActiveInvoice()` và `Order::getActiveInvoice()` định nghĩa chưa đồng nhất

- File liên quan:
  - `app/Models/Order.php`
- Hiện trạng:
  - `hasActiveInvoice()` loại cả `HUY` và `DA_THANH_TOAN`.
  - `getActiveInvoice()` chỉ loại `HUY`, nên invoice đã thanh toán vẫn được xem là active/current.
  - `isInvoiceLocked()` cũng chỉ loại `HUY`, nên invoice đã thanh toán vẫn khóa cước bán.
- Điểm cần chốt:
  - Invoice đã thanh toán có còn khóa cước bán không? Test plan chỉ nói "có hóa đơn active", chưa định nghĩa rõ `DA_THANH_TOAN`.
- Đề xuất:
  - Chốt lại khái niệm active/current/locked:
    - `active`: đang xử lý, chưa final.
    - `current`: invoice mới nhất không hủy hoặc mới nhất bất kỳ.
    - `locked`: trạng thái nào làm khóa cước bán.

### 12. Logic thanh toán trên trang order payment và controller danh sách hóa đơn bị trùng

- File liên quan:
  - `resources/views/pages/order/⚡payment.blade.php`
  - `app/Http/Controllers/Invoice/InvoiceDataTableController.php`
  - `app/Services/OrderInvoiceService.php`
- Hiện trạng:
  - Một số logic cash/QR/reject/confirm được viết trực tiếp ở Livewire page.
  - Controller danh sách hóa đơn có logic tương tự.
- Rủi ro:
  - Sửa một nơi nhưng quên nơi còn lại, dẫn đến lệch hành vi.
- Đề xuất:
  - Dồn các thao tác nghiệp vụ vào service dùng chung:
    - submit cash payment
    - submit online payment
    - regenerate QR
    - reject payment
    - reset payment channel
    - mark paid

## Kiểm tra đã thực hiện

- Đã kiểm tra cú pháp PHP:
  - `app/Services/OrderInvoiceService.php`
  - `app/Models/CongNoPayment.php`
  - `app/Models/Order.php`
  - `app/Http/Controllers/Invoice/InvoiceDataTableController.php`
  - `app/Services/Payments/PaymentInvoiceMatcher.php`
  - `database/migrations/2026_05_27_150000_add_order_columns_to_congno_payments_table.php`
- Kết quả:
  - Không phát hiện lỗi cú pháp.

## Đề xuất thứ tự xử lý

1. Chặn tạo invoice cho đơn không vãng lai.
2. Sửa webhook sync order cho hóa đơn đơn lẻ.
3. Cho phép hủy invoice online đang chờ thanh toán, không phụ thuộc throttle QR.
4. Chốt UX hiển thị invoice đã hủy trên trang payment.
5. Chốt quyền sale với `/hoa-don-thu` và API invoice.
6. Chốt format mã hóa đơn và URL trong test plan.
7. Quyết định regenerate QR dùng code mới hay reuse code cũ.
8. Tách logic nghiệp vụ trùng vào service dùng chung.
