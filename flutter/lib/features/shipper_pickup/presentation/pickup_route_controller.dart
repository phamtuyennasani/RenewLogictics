import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/location/location_service.dart';
import '../domain/route_path.dart';
import '../data/vietmap_api.dart';

/// Giai đoạn của luồng tìm đường trên màn hình bản đồ.
enum RoutePhase { idle, locating, routing, drawn, error }

/// State của màn hình bản đồ chỉ đường pickup.
class PickupRouteState {
  const PickupRouteState({
    this.phase = RoutePhase.idle,
    this.shipper,
    this.route,
    this.errorMessage,
    this.canOpenSettings = false,
  });

  final RoutePhase phase;
  final LatLngPoint? shipper;
  final RoutePath? route;
  final String? errorMessage;

  /// true khi lỗi vị trí cần người dùng mở Cài đặt (quyền/GPS).
  final bool canOpenSettings;

  bool get isBusy =>
      phase == RoutePhase.locating || phase == RoutePhase.routing;

  PickupRouteState copyWith({
    RoutePhase? phase,
    LatLngPoint? shipper,
    RoutePath? route,
    String? errorMessage,
    bool? canOpenSettings,
    bool clearError = false,
  }) {
    return PickupRouteState(
      phase: phase ?? this.phase,
      shipper: shipper ?? this.shipper,
      route: route ?? this.route,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      canOpenSettings: canOpenSettings ?? this.canOpenSettings,
    );
  }
}

/// Điều phối lấy vị trí shipper + gọi VietMap routing tới điểm pickup.
///
/// UI (PickupRouteMapScreen) lắng nghe state để vẽ marker/polyline và hiển thị
/// trạng thái; controller không giữ tham chiếu tới map để dễ test.
class PickupRouteController extends StateNotifier<PickupRouteState> {
  PickupRouteController({
    required VietmapApi vietmapApi,
    required LocationService locationService,
    required this.destination,
    // ignore: prefer_initializing_formals
  }) : _vietmapApi = vietmapApi,
       _location = locationService,
       super(const PickupRouteState());

  final VietmapApi _vietmapApi;
  final LocationService _location;

  /// Điểm pickup cần tới (toạ độ từ Pickup.location).
  final LatLngPoint destination;

  /// Lấy vị trí shipper rồi tìm đường tới [destination].
  Future<void> findRoute() async {
    if (state.isBusy) return;

    state = state.copyWith(phase: RoutePhase.locating, clearError: true);

    final LatLngPoint shipper;
    try {
      shipper = await _location.currentPosition();
    } on LocationFailure catch (e) {
      state = state.copyWith(
        phase: RoutePhase.error,
        errorMessage: e.message,
        canOpenSettings: e.openSettings,
      );
      return;
    }

    state = state.copyWith(
      phase: RoutePhase.routing,
      shipper: shipper,
      canOpenSettings: false,
    );

    try {
      final route = await _vietmapApi.route(
        origin: shipper,
        destination: destination,
      );
      state = state.copyWith(phase: RoutePhase.drawn, route: route);
    } on ApiException catch (e) {
      // Vẫn giữ vị trí shipper đã có; chỉ báo lỗi tuyến.
      state = state.copyWith(phase: RoutePhase.error, errorMessage: e.message);
    }
  }

  /// Mở cài đặt ứng dụng để cấp quyền vị trí.
  Future<void> openLocationSettings() => _location.openAppSettings();
}
