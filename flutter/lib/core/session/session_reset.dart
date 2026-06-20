import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/notifications/presentation/notifications_controller.dart';
import '../../features/ops_orders/presentation/ops_order_detail_screen.dart';
import '../../features/ops_orders/presentation/ops_order_list_controller.dart';
import '../../features/ops_pickups/presentation/ops_pickup_detail_screen.dart';
import '../../features/ops_pickups/presentation/ops_pickup_list_controller.dart';
import '../../features/profile/presentation/profile_edit_controller.dart';
import '../../features/shipper_pickup/presentation/pickup_detail_controller.dart';
import '../../features/shipper_pickup/presentation/pickup_images_controller.dart';
import '../../features/shipper_pickup/presentation/pickup_list_controller.dart';
import '../../features/shipper_scan/presentation/shipper_scan_controller.dart';
import '../../features/ops_scan/presentation/scan_controller.dart';
import '../providers.dart';

/// Các provider GẮN VỚI PHIÊN người dùng (giữ dữ liệu riêng của tài khoản:
/// danh sách, chi tiết, lịch sử quét, thông báo, form...).
///
/// Khi đăng xuất / đổi tài khoản, toàn bộ state này phải bị xóa để tài khoản
/// mới không thấy dữ liệu của tài khoản cũ. Provider là singleton (không
/// autoDispose) nên không tự dọn — ta invalidate thủ công qua danh sách này.
///
/// THÊM CONTROLLER MỚI: chỉ cần khai báo thêm một dòng vào danh sách dưới đây.
/// KHÔNG đưa vào đây:
///  - DI thuần (api/repository/service) — không giữ dữ liệu user.
///  - [authControllerProvider] — chính nó điều khiển phiên, reset riêng.
///  - Provider `.autoDispose` (vd pickupRouteControllerProvider) — tự dọn.
final _sessionScopedProviders = <ProviderOrFamily>[
  notificationsControllerProvider,
  pickupListControllerProvider,
  opsPickupListControllerProvider,
  opsOrderListControllerProvider,
  scanControllerProvider,
  shipperScanControllerProvider,
  profileEditControllerProvider,
  // Family providers — invalidate sẽ xóa mọi instance đã tạo.
  pickupDetailControllerProvider,
  pickupImagesControllerProvider,
  opsOrderDetailControllerProvider,
  opsPickupDetailControllerProvider,
];

/// Xóa toàn bộ state gắn với phiên. Gọi mỗi khi trạng thái đăng nhập đổi
/// (đăng nhập, đăng xuất, hết hạn 401, khôi phục phiên).
void resetSessionState(WidgetRef ref) {
  for (final provider in _sessionScopedProviders) {
    ref.invalidate(provider);
  }
  // Đưa cờ tín hiệu 401 về 0 để phiên mới bắt đầu sạch.
  ref.invalidate(unauthorizedSignalProvider);
}
