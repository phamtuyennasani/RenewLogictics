import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:vietmap_flutter_gl/vietmap_flutter_gl.dart';

import '../../../core/config/mobile_config_provider.dart';
import '../../../core/location/location_service.dart';
import '../../../core/utils/contact_actions.dart';
import '../../../shared/widgets/app_surfaces.dart';
import '../domain/pickup.dart';
import '../domain/route_path.dart';
import 'pickup_providers.dart';
import 'pickup_route_controller.dart';

/// Màn hình bản đồ chỉ đường tới điểm pickup (VietMap GL).
///
/// - Hiển thị marker pickup ngay khi mở.
/// - Nút "Tìm đường": lấy vị trí shipper → gọi VietMap routing → vẽ polyline +
///   marker shipper → fit camera. Đồng nhất UX với bản web.
/// - Tile/style tải trực tiếp từ VietMap bằng tile key (lấy từ /api/mobile/config),
///   routing đi qua proxy backend (giấu geocode key).
class PickupRouteMapScreen extends ConsumerStatefulWidget {
  const PickupRouteMapScreen({super.key, required this.pickup});

  final Pickup pickup;

  @override
  ConsumerState<PickupRouteMapScreen> createState() =>
      _PickupRouteMapScreenState();
}

class _PickupRouteMapScreenState extends ConsumerState<PickupRouteMapScreen> {
  VietmapController? _mapController;
  bool _styleReady = false;

  Circle? _shipperMarker;
  Line? _routeLine;

  LatLngPoint get _destination =>
      LatLngPoint(widget.pickup.location.lat!, widget.pickup.location.lng!);

  String _styleUrl(String tileKey) =>
      'https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=$tileKey';

  @override
  void dispose() {
    // KHÔNG gọi removeCircle/removePolyline ở đây: VietmapGL tự huỷ map +
    // annotation khi widget dispose. Gọi platform-channel async lúc map đang
    // bị tear down gây treo (ANR) khi back về màn trước.
    _mapController = null;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final family = _destination;
    final state = ref.watch(pickupRouteControllerProvider(family));
    final configAsync = ref.watch(mobileConfigProvider);

    // Khi có route mới hoặc vị trí shipper mới → vẽ lại lên map.
    ref.listen<PickupRouteState>(pickupRouteControllerProvider(family), (
      prev,
      next,
    ) {
      if (next.phase == RoutePhase.drawn && next.route != null) {
        _drawRoute(next.shipper!, next.route!);
      }
      if (next.errorMessage != null &&
          next.errorMessage != prev?.errorMessage) {
        _showError(next.errorMessage!, canOpenSettings: next.canOpenSettings);
      }
    });

    return Scaffold(
      appBar: AppBar(title: Text('Chỉ đường · ${widget.pickup.maPickup}')),
      body: configAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, _) => _ConfigError(
          onRetry: () {
            ref.invalidate(mobileConfigProvider);
          },
        ),
        data: (config) {
          if (!config.hasVietmapTileKey) {
            return const _ConfigError(
              message: 'Hệ thống chưa cấu hình VietMap Tile API Key.',
            );
          }
          return Stack(
            children: [
              VietmapGL(
                styleString: _styleUrl(config.vietmapTileApiKey),
                initialCameraPosition: CameraPosition(
                  target: LatLng(_destination.lat, _destination.lng),
                  zoom: 14,
                ),
                // Không đọc cameraPosition ở đâu → tắt tracking để khỏi tốn
                // CPU lắng nghe mỗi lần user pan/zoom bản đồ.
                trackCameraPosition: false,
                onMapCreated: (controller) => _mapController = controller,
                onStyleLoadedCallback: _onStyleLoaded,
              ),
              _InfoPanel(pickup: widget.pickup, route: state.route),
              Positioned(
                left: 16,
                right: 16,
                bottom: 24,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _ActionButton(state: state, onFindRoute: _onFindRoute),
                    const SizedBox(height: 10),
                    _NavigateButton(onNavigate: _openExternalNavigation),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _onStyleLoaded() async {
    _styleReady = true;
    await _drawPickupMarker();
  }

  void _onFindRoute() {
    ref.read(pickupRouteControllerProvider(_destination).notifier).findRoute();
  }

  /// Mở app bản đồ ngoài (Google Maps) dẫn đường turn-by-turn tới điểm pickup.
  Future<void> _openExternalNavigation() async {
    final ok = await ContactActions.openDirections(
      lat: _destination.lat,
      lng: _destination.lng,
    );
    if (!ok && mounted) {
      _showError(
        'Không mở được ứng dụng bản đồ trên thiết bị.',
        canOpenSettings: false,
      );
    }
  }

  Future<void> _drawPickupMarker() async {
    final controller = _mapController;
    if (controller == null || !_styleReady) return;

    // Dùng addCircle thay vì addSymbol: VietMap style không có sprite
    // 'marker-15' nên symbol icon sẽ vô hình. Circle vẽ trực tiếp, luôn hiện.
    await controller.addCircle(
      CircleOptions(
        geometry: LatLng(_destination.lat, _destination.lng),
        circleRadius: 9,
        circleColor: const Color(0xFFDC2626),
        circleStrokeWidth: 3,
        circleStrokeColor: const Color(0xFFFFFFFF),
      ),
    );
  }

  Future<void> _drawRoute(LatLngPoint shipper, RoutePath route) async {
    final controller = _mapController;
    if (controller == null || !_styleReady) return;

    // Xoá tuyến + marker shipper cũ (nếu có) trước khi vẽ lại.
    if (_routeLine != null) {
      await controller.removePolyline(_routeLine!);
      _routeLine = null;
    }
    if (_shipperMarker != null) {
      await controller.removeCircle(_shipperMarker!);
      _shipperMarker = null;
    }

    final coords = route.coordinates
        .map((p) => LatLng(p.lat, p.lng))
        .toList(growable: false);

    if (coords.isNotEmpty) {
      _routeLine = await controller.addPolyline(
        PolylineOptions(
          geometry: coords,
          polylineColor: const Color(0xFF0F766E),
          polylineWidth: 5,
          polylineOpacity: 0.88,
          polylineJoin: 'round',
        ),
      );
    }

    _shipperMarker = await controller.addCircle(
      CircleOptions(
        geometry: LatLng(shipper.lat, shipper.lng),
        circleRadius: 8,
        circleColor: const Color(0xFF2563EB),
        circleStrokeWidth: 3,
        circleStrokeColor: const Color(0xFFFFFFFF),
      ),
    );

    await _fitToRoute(shipper, route);
  }

  Future<void> _fitToRoute(LatLngPoint shipper, RoutePath route) async {
    final controller = _mapController;
    if (controller == null) return;

    var minLat = shipper.lat, maxLat = shipper.lat;
    var minLng = shipper.lng, maxLng = shipper.lng;

    void extend(double lat, double lng) {
      if (lat < minLat) minLat = lat;
      if (lat > maxLat) maxLat = lat;
      if (lng < minLng) minLng = lng;
      if (lng > maxLng) maxLng = lng;
    }

    extend(_destination.lat, _destination.lng);
    for (final p in route.coordinates) {
      extend(p.lat, p.lng);
    }

    await controller.animateCamera(
      CameraUpdate.newLatLngBounds(
        LatLngBounds(
          southwest: LatLng(minLat, minLng),
          northeast: LatLng(maxLat, maxLng),
        ),
        left: 56,
        right: 56,
        top: 120,
        bottom: 180,
      ),
    );
  }

  void _showError(String message, {required bool canOpenSettings}) {
    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Theme.of(context).colorScheme.error,
        action: canOpenSettings
            ? SnackBarAction(
                label: 'Mở Cài đặt',
                textColor: Colors.white,
                onPressed: () => ref
                    .read(pickupRouteControllerProvider(_destination).notifier)
                    .openLocationSettings(),
              )
            : null,
      ),
    );
  }
}

/// Nút tìm đường (đổi nhãn theo phase).
class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.state, required this.onFindRoute});

  final PickupRouteState state;
  final VoidCallback onFindRoute;

  @override
  Widget build(BuildContext context) {
    final label = switch (state.phase) {
      RoutePhase.locating => 'Đang định vị...',
      RoutePhase.routing => 'Đang tìm đường...',
      RoutePhase.drawn => 'Tìm lại đường',
      _ => 'Tìm đường',
    };

    return FilledButton.icon(
      onPressed: state.isBusy ? null : onFindRoute,
      icon: state.isBusy
          ? const SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : const Icon(Icons.directions),
      label: Text(label),
      style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
    );
  }
}

/// Nút mở app bản đồ ngoài để dẫn đường turn-by-turn.
class _NavigateButton extends StatelessWidget {
  const _NavigateButton({required this.onNavigate});

  final VoidCallback onNavigate;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onNavigate,
      icon: const Icon(Icons.navigation_outlined),
      label: const Text('Dẫn đường (mở Google Maps)'),
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(48),
        backgroundColor: Theme.of(context).colorScheme.surface,
      ),
    );
  }
}

/// Panel thông tin pickup + khoảng cách/thời gian khi đã có tuyến.
class _InfoPanel extends StatelessWidget {
  const _InfoPanel({required this.pickup, this.route});

  final Pickup pickup;
  final RoutePath? route;

  static String _distance(double meters) => meters >= 1000
      ? '${(meters / 1000).toStringAsFixed(1)} km'
      : '${meters.round()} m';

  static String _duration(double ms) {
    final minutes = (ms / 60000).round().clamp(1, 1 << 31);
    if (minutes < 60) return '$minutes phút';
    return '${minutes ~/ 60} giờ ${minutes % 60} phút';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final address = pickup.customer.address;

    return SafeArea(
      child: Align(
        alignment: Alignment.topCenter,
        child: AppSurface(
          margin: const EdgeInsets.all(12),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                pickup.customer.displayName,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (address != null && address.isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(address, style: theme.textTheme.bodySmall),
              ],
              if (route != null) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    _Metric(
                      icon: Icons.straighten,
                      value: _distance(route!.distanceMeters),
                    ),
                    const SizedBox(width: 16),
                    _Metric(
                      icon: Icons.schedule,
                      value: _duration(route!.durationMs),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.icon, required this.value});

  final IconData icon;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 16, color: theme.colorScheme.primary),
        const SizedBox(width: 4),
        Text(
          value,
          style: theme.textTheme.bodySmall?.copyWith(
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}

/// Hiển thị khi thiếu cấu hình tile key / lỗi nạp config.
class _ConfigError extends StatelessWidget {
  const _ConfigError({this.message, this.onRetry});

  final String? message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.map_outlined, size: 48, color: Colors.grey),
            const SizedBox(height: 12),
            Text(
              message ?? 'Không tải được cấu hình bản đồ.',
              textAlign: TextAlign.center,
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 12),
              OutlinedButton(onPressed: onRetry, child: const Text('Thử lại')),
            ],
          ],
        ),
      ),
    );
  }
}
