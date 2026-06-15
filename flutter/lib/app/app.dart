import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/notifications/push_notification_service.dart';
import '../core/notifications/push_providers.dart';
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
  }

  @override
  void dispose() {
    // Xóa callback để tránh giữ reference tới widget đã dispose.
    final push = ref.read(pushNotificationServiceProvider);
    if (push.onRouteSelected == _handlePushRoute) {
      push.onRouteSelected = null;
    }
    super.dispose();
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

      default:
        debugPrint('[Push] Unknown type: ${route.type}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'Shipper & OPS',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      routerConfig: router,
    );
  }
}
