import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/notifications/push_notification_service.dart';
import '../core/notifications/push_providers.dart';
import '../core/session/session_reset.dart';
import '../features/auth/presentation/auth_controller.dart';
import '../features/notifications/presentation/notifications_controller.dart';
import '../features/shipper_pickup/presentation/pickup_providers.dart';
import 'router.dart';
import 'theme/app_theme.dart';

/// Root app: MaterialApp.router + theme + router (có auth guard).
class ShipperOpsApp extends ConsumerStatefulWidget {
  const ShipperOpsApp({super.key});

  @override
  ConsumerState<ShipperOpsApp> createState() => _ShipperOpsAppState();
}

class _ShipperOpsAppState extends ConsumerState<ShipperOpsApp> {
  @override
  void initState() {
    super.initState();
    // Gắn handler điều hướng khi user tap notification.
    final push = ref.read(pushNotificationServiceProvider);
    push.onRouteSelected = _handlePushRoute;
    push.onMessageReceived = _handlePushReceived;
  }

  @override
  void dispose() {
    // Xóa callback để tránh giữ reference tới widget đã dispose.
    final push = ref.read(pushNotificationServiceProvider);
    if (push.onRouteSelected == _handlePushRoute) {
      push.onRouteSelected = null;
    }
    if (push.onMessageReceived == _handlePushReceived) {
      push.onMessageReceived = null;
    }
    super.dispose();
  }

  /// Push tới lúc app foreground (chưa tap): làm mới state nền như badge
  /// số thông báo chưa đọc.
  void _handlePushReceived(PushRoute route) {
    if (route.type == 'notification') {
      ref.read(notificationsControllerProvider.notifier).load();
    }
  }

  void _handlePushRoute(PushRoute route) {
    final router = ref.read(routerProvider);

    switch (route.type) {
      case 'pickup_assigned':
        if (route.pickupId != null) {
          router.go(AppRoutes.pickupDetailLocation(route.pickupId!));
        }
        break;

      case 'order_assigned':
        if (route.orderId != null) {
          router.go(AppRoutes.opsOrderDetailLocation(route.orderId!));
        }
        break;

      case 'pickup_assigned_ops':
        if (route.pickupId != null) {
          router.go(AppRoutes.opsPickupDetailLocation(route.pickupId!));
        }
        break;

      case 'notification':
        // Chọn nhánh theo khả năng của user: OPS-capable ưu tiên module OPS,
        // còn lại (shipper) dùng nhánh shipper. Cả hai tái dùng cùng màn hình.
        final ops = ref.read(authControllerProvider).session?.isOpsCapable ??
            false;
        router.go(
          route.newsId != null
              ? AppRoutes.notificationDetailLocation(route.newsId!, ops: ops)
              : AppRoutes.notificationsLocation(ops: ops),
        );
        break;

      default:
        debugPrint('[Push] Unknown type: ${route.type}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(routerProvider);

    // Khởi động/ngừng bộ đồng bộ hàng đợi offline theo trạng thái đăng nhập:
    // đăng nhập xong → lắng nghe mạng + đẩy ngay action còn tồn; logout → ngừng.
    ref.listen<AuthState>(authControllerProvider, (prev, next) {
      if (prev?.status == next.status) return;
      // Đổi phiên (đăng nhập/đăng xuất/hết hạn) → xóa toàn bộ state gắn với
      // tài khoản cũ để tài khoản mới không thấy dữ liệu cũ. Xem danh sách
      // provider trong session_reset.dart.
      resetSessionState(ref);
      final sync = ref.read(pendingStatusSyncProvider);
      if (next.status == AuthStatus.authenticated) {
        sync.start();
        sync.drain();
      } else if (next.status == AuthStatus.unauthenticated) {
        sync.stop();
      }
    });

    return MaterialApp.router(
      title: 'Shipper & OPS',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      routerConfig: router,
    );
  }
}
