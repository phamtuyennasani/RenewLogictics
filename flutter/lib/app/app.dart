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

  void _handlePushRoute(PushRoute route) {
    if (route.type == 'pickup_assigned' && route.pickupId != null) {
      final router = ref.read(routerProvider);
      router.go('/shipper/pickups/${route.pickupId}');
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
