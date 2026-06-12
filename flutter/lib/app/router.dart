import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/domain/user_session.dart';
import '../features/auth/presentation/auth_controller.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/module_chooser_screen.dart';
import '../features/auth/presentation/splash_screen.dart';
import '../features/ops_orders/presentation/create_pickup_screen.dart';
import '../features/ops_orders/presentation/ops_order_detail_screen.dart';
import '../features/ops_orders/presentation/ops_order_list_screen.dart';
import '../features/ops_pickups/presentation/ops_pickup_detail_screen.dart';
import '../features/ops_pickups/presentation/ops_pickup_list_screen.dart';
import '../features/ops_scan/presentation/ops_scanner_screen.dart';
import '../features/ops_scan/presentation/recent_scans_screen.dart';
import '../features/profile/presentation/profile_screen.dart';
import '../features/shipper_pickup/presentation/pickup_detail_screen.dart';
import '../features/shipper_pickup/presentation/pickup_list_screen.dart';

/// Tên các route (tránh hardcode chuỗi rải rác).
abstract class AppRoutes {
  static const splash = '/';
  static const login = '/login';
  static const chooser = '/chooser';
  static const shipper = '/shipper';
  static const pickupDetail = '/shipper/pickups/:id';
  static const pickupDetailChild = 'pickups/:id';
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
  static const profile = '/profile';

  static String pickupDetailLocation(int id) => '/shipper/pickups/$id';
  static String opsOrderDetailLocation(int id) => '/ops/orders/$id';
  static String opsOrderCreatePickupLocation(int id) => '/ops/orders/$id/create-pickup';
  static String opsPickupDetailLocation(int id) => '/ops/pickups/$id';
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

  return GoRouter(
    initialLocation: AppRoutes.splash,
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
            path: AppRoutes.pickupDetailChild,
            builder: (_, state) {
              final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
              return PickupDetailScreen(pickupId: id);
            },
          ),
        ],
      ),
      GoRoute(path: AppRoutes.ops, builder: (_, _) => const OpsScannerScreen()),
      GoRoute(
        path: AppRoutes.opsRecent,
        builder: (_, _) => const RecentScansScreen(),
      ),
      GoRoute(
        path: AppRoutes.opsOrders,
        builder: (_, _) => const OpsOrderListScreen(),
      ),
      GoRoute(
        path: AppRoutes.opsOrderDetail,
        builder: (_, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return OpsOrderDetailScreen(orderId: id);
        },
      ),
      GoRoute(
        path: AppRoutes.opsOrderCreatePickup,
        builder: (_, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          final detail = state.extra;
          return CreatePickupScreen(
            orderId: id,
            orderDetail: detail as dynamic,
          );
        },
      ),
      GoRoute(
        path: AppRoutes.opsPickups,
        builder: (_, _) => const OpsPickupListScreen(),
      ),
      GoRoute(
        path: AppRoutes.opsPickupDetail,
        builder: (_, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return OpsPickupDetailScreen(pickupId: id);
        },
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
