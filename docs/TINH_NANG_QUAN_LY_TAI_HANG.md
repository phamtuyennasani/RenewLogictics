# Quản lý Tải hàng — Tài liệu giới thiệu tính năng

> **Phiên bản:** 1.0
> **Ngày:** 28/05/2026
> **Trạng thái:** Đã triển khai

---

## 1. Tổng quan

**Tải hàng** là một lô vận chuyển gom nhiều đơn hàng đi chung. Người dùng chỉ cần thao tác tại cấp tải — nhập lịch sử vận chuyển hoặc duyệt xuất — hệ thống sẽ tự động đồng bộ lịch sử và cập nhật trạng thái xuống toàn bộ đơn trong tải.

**Mục tiêu:** Giảm thao tác lặp lại khi quản lý nhiều đơn cùng lộ trình.

---

## 2. Luồng hoạt động

```
Tạo tải (packages/create)
  └─ Chọn đơn "Đã nhận hàng" → sinh mã TAI-YYMMDD-NNNN
     └─ 1 transaction: create + attach

Chi tiết tải (packages/{id})
  ├─ Thêm đơn
  │   └─ Chỉ đơn DA_NHAN_HANG, chưa thuộc tải nào
  │       └─ lockForUpdate → validate → attach → sync totals
  │
  ├─ Xóa đơn (chỉ khi tải chưa duyệt)
  │
  ├─ Nhập lịch sử vận chuyển
  │   └─ Tạo shipment_load_history
  │       └─ Đồng bộ order_history cho MỖI đơn trong tải
  │           └─ action='shipment_load_history', metadata chứa mã tải
  │
  └─ Duyệt xuất
      └─ lockForUpdate → validate đơn → cập nhật bill_status
          └─ Mỗi đơn: DA_NHAN_HANG → DUYET_XUAT_HANG + lịch sử
```

---

## 3. Chi tiết từng bước

### 3.1. Tạo tải — `packages/create`

| Bước | Hành động | Kết quả |
|------|-----------|---------|
| 1 | Vào `packages/create` | Danh sách đơn đủ điều kiện |
| 2 | Tích chọn đơn | Đếm & cân nặng cập nhật real-time |
| 3 | Nhấn "Tạo tải hàng" | `CreateShipmentLoadAction::execute(userId, orderIds)` |
| 4 | Sinh mã `TAI-260528-0001` | Transaction: create + attach trong 1 block |
| 5 | Redirect chi tiết tải | Toast thành công |

**Validation khi tạo:**
- Tất cả đơn phải có `bill_status = DA_NHAN_HANG`
- Đơn không được thuộc bất kỳ tải nào (ràng buộc `unique(id_order)`)
- Nếu attach fail → toàn bộ rollback

### 3.2. Thêm đơn vào tải — chi tiết tải, tab "Thêm đơn"

| Bước | Hành động | Kết quả |
|------|-----------|---------|
| 1 | Mở chi tiết tải | Danh sách đơn chưa thuộc tải nào |
| 2 | Tích chọn đơn | Đếm & cân cập nhật |
| 3 | Nhấn "Thêm đơn đã chọn" | `AddOrdersToShipmentLoadAction::execute(load, orderIds)` |
| 4 | `lockForUpdate()` trên tải + đơn | Pessimistic lock |
| 5 | Validate: đơn `DA_NHAN_HANG`? | Chặn đơn sai trạng thái |
| 6 | Validate: đơn chưa thuộc tải `MOI_TAO` nào khác? | Chặn đơn đang ở tải mở khác |
| 7 | Insert `shipment_load_orders` | Ghi `added_by` |
| 8 | `SyncShipmentLoadTotalsAction` | Cập nhật `orders_count`, `total_chargeable_weight` |

**Validation khi thêm:**
- Tải phải đang `MOI_TAO` (chưa duyệt xuất) — kiểm tra qua `canEditOrders()`
- Đơn phải `DA_NHAN_HANG`
- Đơn chưa nằm trong tải `MOI_TAO` nào khác

### 3.3. Xóa đơn khỏi tải

- Chỉ thực hiện khi tải `MOI_TAO` (`canEditOrders()`)
- Xóa khỏi `shipment_load_orders` → `SyncShipmentLoadTotalsAction` cập nhật số liệu

### 3.4. Nhập lịch sử vận chuyển — chi tiết tải, tab "Lịch sử"

| Bước | Hành động | Kết quả |
|------|-----------|---------|
| 1 | Điền form: thời gian, địa điểm, trạng thái, ghi chú | Validate dữ liệu |
| 2 | Nhấn "Ghi lịch sử" | `RecordShipmentLoadHistoryAction::execute(...)` |
| 3 | Tạo bản ghi `shipment_load_histories` | 1 record |
| 4 | Vòng lặp từng đơn trong tải | Tạo bản ghi `order_history` |
| 5 | `action = 'shipment_load_history'` | Đánh dấu bản ghi đồng bộ |
| 6 | JSON `content` gắn metadata | `shipment_load_code`, `shipment_load_id`, `shipment_load_history_id` |

**Tại chi tiết đơn hàng:** lịch sử đồng bộ hiển thị badge tím "Đồng bộ từ tải" + mã tải, phân biệt rõ với lịch sử nhập riêng (dot xanh).

### 3.5. Duyệt xuất tải — chi tiết tải, nút "Duyệt xuất"

| Bước | Hành động | Kết quả |
|------|-----------|---------|
| 1 | Nhấn "Duyệt xuất" (xác nhận modal) | `ApproveShipmentLoadAction::execute(load)` |
| 2 | `lockForUpdate()` trên tải + toàn bộ đơn | Pessimistic lock |
| 3 | Kiểm tra: tải chưa duyệt? | Chặn nếu đã duyệt |
| 4 | Kiểm tra: tải có đơn? | Chặn nếu tải rỗng |
| 5 | Kiểm tra: tất cả đơn `DA_NHAN_HANG`? | Liệt kê đơn lỗi nếu không |
| 6 | Cập nhật tải: `status = DA_DUYET_XUAT` | Ghi `approved_by`, `approved_at` |
| 7 | Vòng lặp đơn: `bill_status = DUYET_XUAT_HANG` | Ghi `ngayxuathang`, tạo lịch sử |
| 8 | `SyncShipmentLoadTotalsAction` | Cập nhật số liệu cuối cùng |

**Sau khi duyệt xuất:**
- Tải → "Đã duyệt xuất" (màu xanh), nút duyệt biến mất
- Không thể thêm/xóa đơn
- Không thể nhập lịch sử tải mới

---

## 4. Mã tải hàng

**Quy tắc:** `TAI-{YYMMDD}-{NNNN}`

| Thành phần | Ý nghĩa | Ví dụ |
|-----------|---------|-------|
| `TAI` | Prefix cố định | `TAI` |
| `YYMMDD` | Ngày tạo (năm-tháng-ngày) | `260528` |
| `NNNN` | Số thứ tự trong ngày, 4 chữ số | `0001` |

Ví dụ: `TAI-260528-0003` = tải thứ 3 tạo ngày 28/05/2026.

Sinh trong `CreateShipmentLoadAction::nextCode()` với `lockForUpdate()` để tránh trùng khi nhiều người tạo cùng lúc.

---

## 5. Trạng thái

### 5.1. Trạng thái tải hàng

| Trạng thái | Enum | Màu UI | Ý nghĩa |
|-----------|------|--------|---------|
| Mới tạo | `MOI_TAO` | Vàng | Tải đang mở, thêm/xóa đơn, nhập lịch sử |
| Đã duyệt xuất | `DA_DUYET_XUAT` | Xanh | Tải đã xuất kho, đơn chuyển trạng thái, không sửa |

### 5.2. Trạng thái đơn liên quan

| Đơn | Vai trò trong tải |
|------|------------------|
| `DA_NHAN_HANG` | Điều kiện để thêm vào tải |
| `DUYET_XUAT_HANG` | Sau khi tải được duyệt xuất |

---

## 6. Mô hình dữ liệu

```
shipment_loads ────< shipment_load_orders
  id                   id
  code (unique)        shipment_load_id (FK)
  status               id_order (FK)
  created_by (FK)      added_by (FK)
  approved_by (FK)     created_at, updated_at
  approved_at          ── unique: id_order
  orders_count         ── unique: [shipment_load_id, id_order]
  total_chargeable_weight
  created_at, updated_at

shipment_loads ────< shipment_load_histories
  id                   id
  status               shipment_load_id (FK)
  code                 id_user (FK)
  created_by           thoigian, diadiem, trangthai, ghichu
                       created_at, updated_at

orders ────< order_history
  id                   id
  bill_status          id_order (FK)
  ngayxuathang         id_user (FK)
  ...                  action
                       content (JSON metadata)
                       thoigian, diadiem, trangthai, ghichu
                       main (boolean)
```

**Đặc biệt tại `order_history`:**

| `action` | Ý nghĩa | Metadata trong `content` |
|---------|---------|----------------------|
| `shipment_load_history` | Đồng bộ từ tải | `shipment_load_code`, `shipment_load_id`, `shipment_load_history_id` |
| `shipment_load_approved` | Duyệt xuất từ tải | `shipment_load_code`, `shipment_load_id` |

---

## 7. Actions

| Action | File | Nhiệm vụ |
|--------|------|---------|
| `CreateShipmentLoadAction` | `app/Actions/ShipmentLoad/` | Sinh mã, tạo tải, attach đơn — trong 1 transaction |
| `AddOrdersToShipmentLoadAction` | `app/Actions/ShipmentLoad/` | Lock, validate, attach đơn, sync totals |
| `RecordShipmentLoadHistoryAction` | `app/Actions/ShipmentLoad/` | Ghi lịch sử tải + đồng bộ xuống mỗi đơn |
| `ApproveShipmentLoadAction` | `app/Actions/ShipmentLoad/` | Duyệt xuất tải + cập nhật trạng thái toàn bộ đơn |
| `SyncShipmentLoadTotalsAction` | `app/Actions/ShipmentLoad/` | Đếm đơn + tổng cân tính phí từ `order_package.c_weight` |

---

## 8. Ràng buộc & Validation

| Ràng buộc | Cấp độ | Chi tiết |
|-----------|--------|---------|
| Đơn phải `DA_NHAN_HANG` | Action | `AddOrdersToShipmentLoadAction`, `CreateShipmentLoadAction` |
| Đơn chưa thuộc tải nào | DB + Action | Unique key + action check tải `MOI_TAO` |
| Tải phải `MOI_TAO` để sửa đơn | Action | `canEditOrders()` |
| Tất cả đơn đủ điều kiện khi duyệt | Action | Liệt kê đơn lỗi nếu không |
| Lock khi thao tác | Action | `lockForUpdate()` trên tải + đơn |
| Tạo + attach đồng thời | Action | 1 `DB::transaction()` trong `CreateShipmentLoadAction` |

---

## 9. Quyền truy cập

| Quyền | Route | Mô tả |
|-------|-------|-------|
| `packages.view` | `GET /packages` | Xem danh sách tải |
| `packages.create` | `GET /packages/create` | Tạo tải mới |
| `packages.view` | `GET /packages/{id}` | Xem chi tiết tải, thêm đơn, nhập lịch sử, duyệt xuất |

---

## 10. Các màn hình

### `packages/` — Danh sách tải
- Bộ lọc: trạng thái, ngày tạo, từ khóa mã tải
- Bảng: mã tải, ngày tạo, người tạo, số đơn, cân nặng, trạng thái
- Hành động: "Chi tiết" → mở tải
- Nút "Tạo tải hàng" → `packages/create`

### `packages/create` — Tạo tải
- Tìm đơn theo mã, tracking, khách hàng
- Danh sách đơn đủ điều kiện (phân trang 15 dòng)
- Checkbox chọn đơn
- Sidebar: đếm đơn + tổng cân tính phí (real-time)
- Nút "Tạo tải hàng" → transaction → redirect chi tiết

### `packages/{id}` — Chi tiết tải
- Header: mã tải, badge trạng thái, người tạo / duyệt
- 3 stat card: số đơn, tổng cân, ngày duyệt xuất
- Tab đơn trong tải: bảng + nút xóa (nếu chưa duyệt)
- Tab thêm đơn: tìm + chọn đơn đủ điều kiện
- Tab lịch sử tải: danh sách + form nhập mới
- Nút "Duyệt xuất" (chỉ khi tải chưa duyệt)

### `orders/{id}` — Chi tiết đơn hàng (component `edit-history`)
- Lịch sử riêng: dot xanh, hiển thị người nhập
- Lịch sử đồng bộ từ tải: dot tím, badge "Đồng bộ từ tải TAI-..."
- Modal toàn bộ: phân biệt rõ nguồn gốc từng bản ghi

---

## 11. Giới hạn & Lưu ý

1. **Một đơn thuộc một tải** — ràng buộc `unique(id_order)` trên pivot ngăn đơn nằm trong 2 tải bất kỳ. Nếu cần cho phép tái sử dụng đơn sau khi tải cũ duyệt, cần xóa ràng buộc và chỉ kiểm tra ở tầng ứng dụng.

2. **Không có hoàn tác duyệt xuất** — cần can thiệp trực tiếp trên DB nếu cần revert.

3. **Chưa tích hợp scan barcode** — màn hình `/scan` là module riêng. Cần bổ sung nếu muốn xác nhận đơn bằng barcode khi thêm vào tải.

4. **Chưa tích hợp TrackingMore** — `package_delivery_status` và ảnh pickup/xuất kho chưa được cập nhật tự động. Lịch sử tải hiện chỉ ghi thủ công.

5. **Chưa có export/báo cáo tải hàng.**
