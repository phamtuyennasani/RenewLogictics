import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/domain/user_session.dart';
import '../features/auth/presentation/auth_controller.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/module_chooser_screen.dart';
import '../features/auth/presentation/splash_screen.dart';
import '../features/notifications/domain/app_notification.dart';
import '../features/notifications/presentation/notification_detail_screen.dart';
import '../features/notifications/presentation/notifications_screen.dart';
import '../features/ops_orders/presentation/create_pickup_screen.dart';
import '../features/ops_orders/presentation/ops_order_detail_screen.dart';
import '../features/ops_orders/presentation/ops_order_list_screen.dart';
import '../features/ops_pickups/presentation/ops_pickup_detail_screen.dart';
import '../features/ops_pickups/presentation/ops_pickup_list_screen.dart';
import '../features/ops_scan/presentation/ops_scanner_screen.dart';
import '../features/ops_scan/presentation/ops_shell_screen.dart';
import '../features/ops_scan/presentation/recent_scans_screen.dart';
import '../features/profile/presentation/profile_screen.dart';
import '../features/shipper_pickup/domain/pickup.dart';
import '../features/shipper_pickup/presentation/pickup_detail_screen.dart';
import '../features/shipper_pickup/presentation/pickup_list_screen.dart';
import '../features/shipper_pickup/presentation/pickup_route_map_screen.dart';
import '../features/shipper_scan/presentation/shipper_scanner_screen.dart';

/// Tên các route (tránh hardcode chuỗi rải rác).
abstract class AppRoutes {
  static const splash = '/';
  static const login = '/login';
  static const chooser = '/chooser';
  static const shipper = '/shipper';
  static const shipperScan = '/shipper/scan';
  static const shipperScanChild = 'scan';
  static const shipperNotifications = '/shipper/notifications';
  static const shipperNotificationsChild = 'notifications';
  static const shipperNotificationDetail = '/shipper/notifications/:id';
  static const pickupDetail = '/shipper/pickups/:id';
  static const pickupDetailChild = 'pickups/:id';
  static const pickupRoute = '/shipper/pickups/:id/route';
  static const pickupRouteChild = 'route';
  static const ops = '/ops';
  static const opsRecent = '/ops/recent';
  static const opsOrders = '/ops/orders';
  static const opsOrderDetail = '/ops/orders/:id';
  static const opsOrderDetailChild = 'orders/:id';
  static const opsOrderCreatePickup = '/ops/orders/:id/create-pickup';
  static const opsOrderCreatePickupChild = 'create-pickup';
  static const opsPickups = '/ops/pickups';
  static const opsPickupDetail = '/ops/pickups/:id';
  static const opsPickupDetailChild = 'pickups/:id';
  static const opsNotifications = '/ops/notifications';
  static const opsNotificationDetail = '/ops/notifications/:id';
  static const opsAccount = '/ops/account';
  static const profile = '/profile';

  static String pickupDetailLocation(int id) => '/shipper/pickups/$id';
  static String pickupRouteLocation(int id) => '/shipper/pickups/$id/route';
  static String opsOrderDetailLocation(int id) => '/ops/orders/$id';
  static String opsOrderCreatePickupLocation(int id) =>
      '/ops/orders/$id/create-pickup';
  static String opsPickupDetailLocation(int id) => '/ops/pickups/$id';
  static String opsNotificationDetailLocation(int id) =>
      '/ops/notifications/$id';
  static String shipperNotificationDetailLocation(int id) =>
      '/shipper/notifications/$id';

  /// Chọn nhánh thông báo theo phân quyền (OPS dùng /ops, còn lại /shipper).
  /// Gom về một nơi để điều hướng thông báo nhất quán giữa push và tap.
  static String notificationsLocation({required bool ops}) =>
      ops ? opsNotifications : shipperNotifications;
  static String notificationDetailLocation(int id, {required bool ops}) => ops
      ? opsNotificationDetailLocation(id)
      : shipperNotificationDetailLocation(id);
}

/// Router toàn app + auth guard.
///
/// Guard phản ứng theo [AuthStatus] (Notifier):
/// - unknown → splash (đang khôi phục phiên).
/// - unauthenticated → login.
/// - authenticated → điều hướng theo `default_module` (shipper/ops/chooser).
///
/// Tín hiệu 401 được [AuthController] xử lý (đẩy state về unauthenticated),
/// guard sẽ tự redirect về login khi state đổi.
final routerProvider = Provider<GoRouter>((ref) {
  // Đối tượng lắng nghe thay đổi auth để router refresh redirect.
  final refresh = _AuthRefreshNotifier(ref);
  ref.onDispose(refresh.dispose);

  // Navigator gốc: dùng để push các màn full-screen (chi tiết, form) đè lên
  // shell OPS, che bottom navigation.
  final rootNavigatorKey = GlobalKey<NavigatorState>();

  return GoRouter(
    initialLocation: AppRoutes.splash,
    navigatorKey: rootNavigatorKey,
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final status = auth.status;
      final loc = state.matchedLocation;

      // Đang khôi phục phiên: giữ ở splash.
      if (status == AuthStatus.unknown) {
        return loc == AppRoutes.splash ? null : AppRoutes.splash;
      }

      final loggedIn = status == AuthStatus.authenticated;
      final atAuthFlow = loc == AppRoutes.login || loc == AppRoutes.splash;

      // Chưa đăng nhập: ép về login.
      if (!loggedIn) {
        return loc == AppRoutes.login ? null : AppRoutes.login;
      }

      // Đã đăng nhập nhưng đang ở splash/login → đưa tới module mặc định.
      if (atAuthFlow) {
        return _homeFor(auth.session);
      }

      // Chặn truy cập module không thuộc quyền.
      final session = auth.session;
      if (session != null) {
        if (loc.startsWith(AppRoutes.shipper) && !session.isShipper) {
          return _homeFor(session);
        }
        if (loc.startsWith(AppRoutes.ops) && !session.isOpsCapable) {
          return _homeFor(session);
        }
      }

      return null;
    },
    routes: [
      GoRoute(path: AppRoutes.splash, builder: (_, _) => const SplashScreen()),
      GoRoute(path: AppRoutes.login, builder: (_, _) => const LoginScreen()),
      GoRoute(
        path: AppRoutes.chooser,
        builder: (_, _) => const ModuleChooserScreen(),
      ),
      GoRoute(
        path: AppRoutes.shipper,
        builder: (_, _) => const PickupListScreen(),
        routes: [
          GoRoute(
            path: AppRoutes.shipperScanChild,
            builder: (_, _) => const ShipperScannerScreen(),
          ),
          GoRoute(
            path: AppRoutes.shipperNotificationsChild,
            builder: (_, _) => const NotificationsScreen(),
            routes: [
              GoRoute(
                path: ':id',
                parentNavigatorKey: rootNavigatorKey,
                builder: (_, state) {
                  return NotificationDetailScreen(
                    notification: state.extra is AppNotification
                        ? state.extra as AppNotification
                        : null,
                    notificationId: int.tryParse(
                      state.pathParameters['id'] ?? '',
                    ),
                  );
                },
              ),
            ],
          ),
          GoRoute(
            path: AppRoutes.pickupDetailChild,
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
              return PickupDetailScreen(pickupId: id);
            },
            routes: [
              GoRoute(
                path: AppRoutes.pickupRouteChild,
                builder: (_, state) {
                  return PickupRouteMapScreen(pickup: state.extra as Pickup);
                },
              ),
            ],
          ),
        ],
      ),
      // Module OPS: bottom navigation 5 tab với nút quét QR nổi giữa.
      // Mỗi branch giữ state riêng (indexedStack). Màn chi tiết/form push lên
      // root navigator để che bottom bar.
      StatefulShellRoute.indexedStack(
        builder: (_, _, navigationShell) =>
            OpsShellScreen(navigationShell: navigationShell),
        branches: [
          // 0 — Đơn hàng
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.opsOrders,
                builder: (_, _) => const OpsOrderListScreen(),
                routes: [
                  GoRoute(
                    path: ':id',
                    parentNavigatorKey: rootNavigatorKey,
                    builder: (_, state) {
                      final id =
                          int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
                      return OpsOrderDetailScreen(orderId: id);
                    },
                    routes: [
                      GoRoute(
                        path: AppRoutes.opsOrderCreatePickupChild,
                        parentNavigatorKey: rootNavigatorKey,
                        builder: (_, state) {
                          final id =
                              int.tryParse(state.pathParameters['id'] ?? '') ??
                              0;
                          return CreatePickupScreen(
                            orderId: id,
                            orderDetail: state.extra as dynamic,
                          );
                        },
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
          // 1 — Pickup
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.opsPickups,
                builder: (_, _) => const OpsPickupListScreen(),
                routes: [
                  GoRoute(
                    path: ':id',
                    parentNavigatorKey: rootNavigatorKey,
                    builder: (_, state) {
                      final id =
                          int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
                      return OpsPickupDetailScreen(pickupId: id);
                    },
                  ),
                ],
              ),
            ],
          ),
          // 2 — Quét QR (nút nổi giữa)
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.ops,
                builder: (_, _) => const OpsScannerScreen(),
                routes: [
                  GoRoute(
                    path: 'recent',
                    parentNavigatorKey: rootNavigatorKey,
                    builder: (_, _) => const RecentScansScreen(),
                  ),
                ],
              ),
            ],
          ),
          // 3 — Thông báo
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.opsNotifications,
                builder: (_, _) => const NotificationsScreen(),
                routes: [
                  GoRoute(
                    path: ':id',
                    parentNavigatorKey: rootNavigatorKey,
                    builder: (_, state) {
                      return NotificationDetailScreen(
                        notification: state.extra is AppNotification
                            ? state.extra as AppNotification
                            : null,
                        notificationId: int.tryParse(
                          state.pathParameters['id'] ?? '',
                        ),
                      );
                    },
                  ),
                ],
              ),
            ],
          ),
          // 4 — Tài khoản
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.opsAccount,
                builder: (_, _) => const ProfileScreen(),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: AppRoutes.profile,
        builder: (_, _) => const ProfileScreen(),
      ),
    ],
  );
});

/// Trang chủ theo quyền của phiên.
String _homeFor(UserSession? session) {
  if (session == null) return AppRoutes.login;
  switch (session.defaultModule) {
    case DefaultModule.shipper:
      return AppRoutes.shipper;
    case DefaultModule.ops:
      return AppRoutes.ops;
    case DefaultModule.chooser:
      return AppRoutes.chooser;
    case DefaultModule.unknown:
      // Fallback theo role nếu backend không trả default_module rõ ràng.
      if (session.isShipper && session.isOpsCapable) {
        return AppRoutes.chooser;
      }
      if (session.isShipper) return AppRoutes.shipper;
      if (session.isOpsCapable) return AppRoutes.ops;
      return AppRoutes.profile;
  }
}

/// Cầu nối: lắng nghe [authControllerProvider] và notify GoRouter refresh.
class _AuthRefreshNotifier extends ChangeNotifier {
  _AuthRefreshNotifier(Ref ref) {
    _sub = ref.listen<AuthState>(
      authControllerProvider,
      (_, _) => notifyListeners(),
      fireImmediately: false,
    );
  }

  late final ProviderSubscription<AuthState> _sub;

  @override
  void dispose() {
    _sub.close();
    super.dispose();
  }
}
