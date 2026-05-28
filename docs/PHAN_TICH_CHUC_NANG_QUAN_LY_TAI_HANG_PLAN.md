# Plan phân tích chức năng Quản lý tải hàng

## 1. Mục tiêu

Phân tích và đặc tả chức năng **Quản lý tải hàng** theo nhu cầu gom nhiều đơn hàng vào cùng một tải/lô vận chuyển. Người dùng chỉ cần cập nhật lịch sử vận chuyển hoặc duyệt xuất tại cấp tải, hệ thống sẽ đồng bộ lịch sử và trạng thái xuống toàn bộ đơn hàng trong tải.

## 2. Hiện trạng nhanh

- Menu sidebar đã có mục **Quản lý tải hàng** trỏ tới route `packages.index`.
- Route `/packages` đã được khai báo trong `routes/web.php`, nhưng hiện chỉ render `view('packages.index')`.
- Chưa thấy file view `resources/views/packages/index.blade.php` hoặc Livewire page riêng cho module packages.
- Dữ liệu kiện hàng hiện đang gắn với đơn hàng qua `OrderPackage`, nhưng chưa có entity riêng cho tải/lô gom nhiều đơn.
- Các thao tác tạo và sửa kiện đang nằm trong luồng đơn hàng:
  - Tạo đơn: `App\Actions\Order\CreateOrderAction::createPackages`.
  - Sửa kiện trong chi tiết đơn: `resources/views/components/order/⚡shipment-metrics.blade.php`.
- Quyền đã có trong `PermissionEnum`: `packages.view`, `packages.create`, `packages.update`, `packages.scan`.
- Trạng thái đơn hàng hiện có:
  - `da_nhan_hang`: đơn đủ điều kiện thêm vào tải.
  - `duyet_xuat_hang`: trạng thái cần chuyển sang khi tải được duyệt xuất.

## 3. Đặc tả nghiệp vụ tải hàng

### 3.1. Khái niệm

**Tải hàng** là một lô vận chuyển gom nhiều đơn hàng đi chung. Tải không thay thế đơn hàng; tải là lớp vận hành ở trên đơn hàng để giảm thao tác cập nhật lặp lại.

### 3.2. Thông tin tải hàng

Một tải hàng cần có các thông tin tối thiểu:

- Mã tải.
- Ngày tạo.
- Người tạo.
- Số lượng đơn.
- Tổng cân nặng tính phí.
- Trạng thái tải:
  - `moi_tao`: Mới tạo.
  - `da_duyet_xuat`: Đã duyệt xuất.

### 3.3. Quy tắc thêm đơn vào tải

- Chỉ cho phép thêm các đơn có `bill_status = da_nhan_hang`.
- Một đơn chỉ được nằm trong một tải đang hoạt động tại một thời điểm.
- Không cho thêm đơn đã hủy, đã giao, hoàn hàng hoặc đã ở trạng thái sau `duyet_xuat_hang`.
- Khi tải đã `da_duyet_xuat`, không cho thêm hoặc xóa đơn khỏi tải nếu không có quyền quản trị/rollback rõ ràng.
- Tổng cân nặng tính phí của tải được tính từ tổng `c_weight` của các kiện trong các đơn thuộc tải.

### 3.4. Cập nhật lịch sử vận chuyển

- Người dùng nhập lịch sử vận chuyển tại màn hình chi tiết tải.
- Mỗi lịch sử tạo ở cấp tải phải được ghi xuống lịch sử của toàn bộ đơn thuộc tải.
- Lịch sử ở đơn hàng vẫn có thể nhập riêng lẻ như hiện tại.
- Lịch sử được đồng bộ từ tải cần có metadata để truy vết:
  - Mã tải.
  - ID tải.
  - Người nhập.
  - Thời điểm nhập.
  - Nội dung lịch sử.
- Khi xem chi tiết đơn hàng, người dùng phải thấy cả lịch sử nhập riêng của đơn và lịch sử đồng bộ từ tải.

### 3.5. Duyệt xuất tải

- Khi chuyển tải từ `moi_tao` sang `da_duyet_xuat`, toàn bộ đơn trong tải chuyển `bill_status` từ `da_nhan_hang` sang `duyet_xuat_hang`.
- Việc chuyển trạng thái cần ghi lịch sử cho từng đơn.
- Nếu một đơn trong tải không còn ở `da_nhan_hang` tại thời điểm duyệt, hệ thống phải chặn duyệt và hiển thị danh sách đơn lỗi.
- Duyệt xuất cần chạy trong database transaction để tránh tải đã duyệt nhưng đơn cập nhật thiếu.

## 4. Phạm vi phân tích

### 4.1. Nghiệp vụ

- Định nghĩa tải hàng là lô gom nhiều đơn đi chung.
- Các vai trò sử dụng: admin, manager, ops, cs.
- Trạng thái liên quan:
  - Trạng thái đơn hàng `OrderStatusEnum`.
  - Trạng thái tải hàng mới cần bổ sung.
  - Trạng thái giao kiện `package_delivery_status`.
  - Các mốc ngày: nhận hàng, xuất hàng, giao hàng, dự kiến giao.
- Quy trình từ đơn hàng sang tải hàng:
  1. Đơn được tạo với danh sách kiện.
  2. Đơn chuyển sang trạng thái `da_nhan_hang`.
  3. Ops/CS gom nhiều đơn vào một tải.
  4. Người dùng nhập lịch sử vận chuyển tại tải.
  5. Hệ thống đồng bộ lịch sử xuống từng đơn trong tải.
  6. Người dùng duyệt xuất tải.
  7. Hệ thống chuyển toàn bộ đơn trong tải sang `duyet_xuat_hang`.

### 4.2. Dữ liệu

Các bảng/model cần kiểm tra:

- Bảng tải hàng mới, đề xuất: `shipment_loads` hoặc `package_loads`.
- Bảng pivot tải - đơn mới, đề xuất: `shipment_load_orders` hoặc `package_load_orders`.
- `orders`: thông tin đơn, trạng thái, ngày vận hành, tracking, DIM.
- `order_package`: kiện hàng chính, mã kiện, kích thước, cân nặng, tracking, trạng thái giao.
- `order_package_daily`: dữ liệu kiện theo đại lý.
- `order_package_thucte`: dữ liệu kiện thực tế.
- `order_photo_pickup`: ảnh lấy hàng.
- `order_photo_xuatkho`: ảnh xuất kho.
- `order_history`: lịch sử chỉnh sửa và trạng thái.

Trường đề xuất cho bảng tải hàng:

- `id`, `code`, `status`.
- `created_by`, `approved_by`.
- `approved_at`.
- `orders_count`.
- `total_chargeable_weight`.
- `created_at`, `updated_at`.

Trường đề xuất cho bảng pivot tải - đơn:

- `load_id`, `order_id`.
- `added_by`, `added_at`.
- Unique key trên `order_id` nếu một đơn chỉ được nằm trong một tải hoạt động.

Trường trọng tâm ở dữ liệu hiện có:

- `code`, `id_order`, `package_type`.
- `length`, `width`, `height`, `g_weight`, `v_weight`, `c_weight`, `re_weight`.
- `tracking_id`, `id_thamchieu`, `mathamchieu`.
- `package_delivery_status`, `package_delivered_at`, `package_delivery_synced_at`.

### 4.3. UI/UX cần phân tích

- Trang danh sách tải hàng:
  - Bộ lọc theo mã tải, trạng thái tải, ngày tạo, người tạo.
  - Bảng tải hàng có mã tải, ngày tạo, người tạo, số lượng đơn, tổng cân nặng tính phí, trạng thái.
  - Hành động nhanh: xem chi tiết, thêm đơn, nhập lịch sử, duyệt xuất.
- Màn hình chi tiết tải hàng:
  - Thông tin tải.
  - Danh sách đơn trong tải.
  - Tổng hợp kiện và cân nặng tính phí.
  - Form nhập lịch sử vận chuyển cho tải.
  - Lịch sử đã nhập ở cấp tải.
  - Nút duyệt xuất.
- Màn hình thêm đơn vào tải:
  - Chỉ hiển thị đơn đang `da_nhan_hang`.
  - Cho tìm theo mã đơn, khách hàng, tracking, ngày nhận hàng.
  - Cảnh báo đơn không đủ điều kiện nếu người dùng nhập mã trực tiếp.
- Màn hình scan:
  - Kiểm tra module `/scan` hiện có.
  - Xác định có cần tích hợp scan vào `/packages` hay giữ module riêng.

### 4.4. Quyền và kiểm soát

- Đối chiếu middleware `can:packages.index` với `PermissionEnum`.
- Kiểm tra mapping trong `AuthServiceProvider`.
- Xác định quyền chi tiết:
  - Xem tải hàng.
  - Tạo tải hàng.
  - Thêm/xóa đơn khỏi tải.
  - Nhập lịch sử tải.
  - Duyệt xuất tải.
  - Scan barcode.
  - Xuất dữ liệu.

## 5. Khoảng trống cần xác minh

- `packages.index` chưa có view/page thực tế.
- Chưa thấy controller/datatable riêng cho packages.
- Chưa có bảng/model riêng để đại diện cho tải gom nhiều đơn.
- Chưa có cơ chế đồng bộ lịch sử từ tải xuống đơn.
- Chưa có cơ chế duyệt xuất tải và cập nhật trạng thái hàng loạt sang `duyet_xuat_hang`.
- Chưa rõ luồng ảnh pickup/xuất kho đã được dùng ở UI nào.
- Chưa rõ trạng thái `package_delivery_status` được cập nhật từ người dùng hay đồng bộ TrackingMore.
- GitNexus FTS đang thiếu chỉ mục keyword, nên cần rebuild index nếu tiếp tục phân tích sâu bằng GitNexus.

## 6. Đề xuất module kỹ thuật

### 6.1. Model/Bảng mới

- `ShipmentLoad` hoặc `PackageLoad`: đại diện tải hàng.
- `ShipmentLoadOrder` hoặc pivot relation: liên kết tải với đơn.
- Có thể bổ sung `ShipmentLoadHistory` nếu muốn lưu lịch sử cấp tải riêng, ngoài việc ghi xuống `order_history`.

### 6.2. Action/Service đề xuất

- `CreateShipmentLoadAction`: tạo tải và sinh mã tải.
- `AddOrdersToShipmentLoadAction`: validate và thêm nhiều đơn vào tải.
- `RecordShipmentLoadHistoryAction`: ghi lịch sử tải và đồng bộ xuống từng đơn.
- `ApproveShipmentLoadAction`: duyệt xuất tải, chuyển toàn bộ đơn sang `duyet_xuat_hang`, ghi lịch sử.

### 6.3. Route/Page đề xuất

- `GET /packages`: danh sách tải hàng.
- `GET /packages/create`: tạo tải.
- `GET /packages/{load}`: chi tiết tải.
- `POST /packages/{load}/orders`: thêm đơn vào tải.
- `DELETE /packages/{load}/orders/{order}`: xóa đơn khỏi tải khi tải còn mới tạo.
- `POST /packages/{load}/histories`: nhập lịch sử vận chuyển.
- `POST /packages/{load}/approve`: duyệt xuất tải.

## 7. Kế hoạch thực hiện phân tích chi tiết

1. Khảo sát route, sidebar, gate, permission cho module packages.
2. Lập ERD cho `orders`, `order_package`, `order_history` và bảng tải hàng mới.
3. Trace luồng tạo kiện từ `CreateOrderAction`.
4. Trace luồng sửa kiện từ component `shipment-metrics`.
5. Trace cách ghi lịch sử đơn hiện tại để tái sử dụng khi đồng bộ lịch sử tải.
6. Kiểm tra module scan hiện tại để xác định khả năng tái sử dụng.
7. Chốt quy tắc một đơn thuộc một tải hay nhiều tải theo thời gian.
8. Đề xuất màn hình danh sách tải hàng và chi tiết tải hàng.
9. Đề xuất API/controller/datatable/action cần thêm.
10. Lập checklist test: quyền, filter, thêm đơn, tính cân nặng, nhập lịch sử, duyệt xuất, rollback transaction.

## 8. Test plan sơ bộ

- Tạo tải mới thành công với trạng thái `moi_tao`.
- Chỉ thêm được đơn có `bill_status = da_nhan_hang`.
- Không thêm được đơn đã thuộc tải khác đang hoạt động.
- Tổng số đơn và tổng cân nặng tính phí cập nhật đúng sau khi thêm/xóa đơn.
- Nhập lịch sử tải tạo lịch sử tương ứng cho toàn bộ đơn trong tải.
- Lịch sử đồng bộ từ tải hiển thị trong chi tiết từng đơn.
- Duyệt xuất tải chuyển tải sang `da_duyet_xuat`.
- Duyệt xuất tải chuyển toàn bộ đơn từ `da_nhan_hang` sang `duyet_xuat_hang`.
- Nếu có đơn không còn đủ điều kiện khi duyệt, transaction bị chặn và không cập nhật nửa chừng.
- Sau khi duyệt xuất, không thể thêm/xóa đơn nếu không có quyền đặc biệt.

## 9. Output mong muốn sau phân tích

- Tài liệu đặc tả nghiệp vụ.
- Sơ đồ dữ liệu liên quan.
- Danh sách gap kỹ thuật.
- Wireframe hoặc mô tả UI.
- Plan triển khai theo phase.
- Test plan cho chức năng Quản lý tải hàng.
