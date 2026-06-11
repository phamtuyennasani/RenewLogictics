# Phân tích giao diện mobile app Flutter

Phạm vi: đối chiếu UI trong `flutter/lib` với luồng tại `flutter/docs/APP_FLOW.md`.

## 1. Tổng quan

App hiện là một mobile workflow nội bộ cho hai nhóm chính:

- Shipper: xem pickup, tìm kiếm, đổi trạng thái, gọi khách, mở bản đồ.
- OPS: quét mã/nhập mã thủ công, tra cứu đơn, xác nhận nhập kho, xem lịch sử phiên.

Kiến trúc UI đang bám theo `APP_FLOW.md`: router có auth guard, điều hướng theo `default_module`, và chia feature-first theo `auth`, `shipper_pickup`, `ops_scan`, `profile`.

## 2. Bản đồ màn hình

| Màn hình | File | Vai trò UI |
| --- | --- | --- |
| Splash | `lib/features/auth/presentation/splash_screen.dart` | Khôi phục phiên, hiển thị tên app và loading |
| Login | `lib/features/auth/presentation/login_screen.dart` | Đăng nhập username/password, lỗi qua SnackBar |
| Module chooser | `lib/features/auth/presentation/module_chooser_screen.dart` | Chọn Shipper hoặc OPS khi user có nhiều quyền |
| Pickup list | `lib/features/shipper_pickup/presentation/pickup_list_screen.dart` | 4 tab pickup, search debounce, refresh, infinite scroll |
| Pickup detail | `lib/features/shipper_pickup/presentation/pickup_detail_screen.dart` | Thông tin khách, số kiện/đơn/kg, ghi chú, orders, cập nhật trạng thái |
| Status action sheet | `lib/features/shipper_pickup/presentation/widgets/status_action_sheet.dart` | Chọn trạng thái tiếp theo, bắt buộc lý do khi hủy |
| OPS scanner | `lib/features/ops_scan/presentation/ops_scanner_screen.dart` | Camera scanner, nhập mã thủ công, flash/camera switch, điều hướng lịch sử/profile |
| Scan result | `lib/features/ops_scan/presentation/widgets/scan_result_card.dart` | Kết quả tìm đơn, trạng thái nhận kho, CTA xác nhận nhập kho |
| Recent scans | `lib/features/ops_scan/presentation/recent_scans_screen.dart` | Lịch sử quét trong phiên, xác nhận trước khi xóa |
| Profile | `lib/features/profile/presentation/profile_screen.dart` | Hồ sơ ngắn, vai trò, đăng xuất |

## 3. Điểm mạnh UX

- Luồng tác vụ chính ngắn: mở app -> vào module mặc định -> thao tác nghiệp vụ.
- Shipper list phù hợp hiện trường: tab theo trạng thái, search theo mã/tên/SĐT/địa chỉ, pull-to-refresh, infinite scroll.
- Pickup detail có CTA cố định ở đáy màn hình nên thao tác đổi trạng thái dễ chạm.
- OPS scanner có fallback nhập mã thủ công khi camera lỗi hoặc chưa cấp quyền.
- Scan result tách rõ hai trạng thái: tìm thấy/không tìm thấy, được nhập kho/không được nhập kho.
- Lịch sử scan chỉ lưu trong phiên, khớp yêu cầu bảo mật dữ liệu nhạy cảm trong `APP_FLOW.md`.

## 4. Rủi ro và điểm cần cải thiện

1. Login chưa có liên kết cấu hình môi trường/API endpoint cho tester. Nếu sai `.env`, người dùng chỉ thấy lỗi đăng nhập chung.
2. Module chooser dùng card lớn và đơn giản, nhưng chưa có mô tả quyền hoặc trạng thái disabled nếu một module bị guard chặn.
3. Pickup list có empty state nhưng chưa thấy quick action như refresh/tắt filter, dễ gây hiểu nhầm khi keyword đang lọc.
4. Pickup card đang hiển thị gọn, nhưng thiếu nhãn ưu tiên cho thời gian hẹn gần nhất ở từng item; shipper có thể phải vào chi tiết để quyết định thứ tự đi.
5. Status action sheet phụ thuộc label backend; nếu backend trả label dài, CTA có nguy cơ chật trên màn hình nhỏ.
6. OPS scanner đặt camera và form thủ công cùng một màn hình, tốt cho vận hành, nhưng cần kiểm tra thực tế trên máy nhỏ để bảo đảm camera preview không đẩy CTA khỏi vùng chạm thuận tiện.
7. Lỗi/thành công dùng SnackBar nhiều. Với thao tác nghiệp vụ quan trọng như nhập kho, nên cân nhắc trạng thái inline hoặc confirmation rõ hơn để tránh bị bỏ lỡ.
8. Chưa thấy widget test cho các state UI chính: empty/error/loading của pickup list, scan not found, scan can_receive=false, camera permission denied.

## 5. Khuyến nghị ưu tiên

### P0 - kiểm tra trước khi giao vận hành

- Chạy UI trên thiết bị nhỏ và thiết bị có notch để kiểm tra bottom CTA, scanner preview, keyboard khi nhập mã thủ công.
- Bổ sung widget tests cho state quan trọng của Shipper và OPS.
- Thêm kiểm tra hiển thị khi text backend dài: status label, reason, địa chỉ, tên công ty.

### P1 - cải thiện trải nghiệm

- Pickup list: khi không có dữ liệu do search/filter, hiển thị rõ keyword/tab hiện tại và nút xóa tìm kiếm.
- Pickup card: làm nổi bật `scheduledAt`, địa chỉ và trạng thái để shipper ra quyết định ngay ở list.
- Scan result: thêm vùng feedback inline sau khi nhập kho thành công, không chỉ SnackBar.
- Module chooser: hiển thị vai trò hiện tại hoặc ghi chú ngắn về quyền truy cập.

### P2 - hoàn thiện polish

- Chuẩn hóa copy tiếng Việt giữa "nhập kho", "xác nhận nhập kho", "tra cứu".
- Rà soát kích thước touch target cho icon-only buttons: flash, đổi camera, lịch sử, profile.
- Cân nhắc dark mode nếu app dùng ngoài kho/bãi có ánh sáng yếu.

## 6. Tình trạng kỹ thuật

- `flutter analyze` chạy được và báo 14 issue mức info/lint, không thấy lỗi UI nghiêm trọng.
- Test hiện có thiên về parsing/core model; coverage UI còn mỏng.
- Theme Material 3 dùng seed xanh logistics, nền sáng, card không elevation; phù hợp app vận hành nhưng cần kiểm tra contrast thực tế với màu status từ backend.

