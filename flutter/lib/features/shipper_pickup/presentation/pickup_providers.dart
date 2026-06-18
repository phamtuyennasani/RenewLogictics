import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/location/location_service.dart';
import '../../../core/network/connectivity_service.dart';
import '../../../core/providers.dart';
import '../data/pending_status_store.dart';
import '../data/pending_status_sync.dart';
import '../data/pickup_api.dart';
import '../data/pickup_repository_impl.dart';
import '../data/vietmap_api.dart';
import '../domain/pickup_repository.dart';
import 'pickup_route_controller.dart';

/// DI cho feature shipper pickup.
final pickupApiProvider = Provider<PickupApi>((ref) {
  return PickupApi(ref.watch(dioClientProvider));
});

final pickupRepositoryProvider = Provider<PickupRepository>((ref) {
  return PickupRepositoryImpl(ref.watch(pickupApiProvider));
});

/// Service trạng thái mạng (online/offline) — dùng cho hàng đợi offline.
final connectivityServiceProvider = Provider<ConnectivityService>((ref) {
  return ConnectivityService();
});

/// Hàng đợi thao tác đổi trạng thái pickup chờ đồng bộ (lưu shared_preferences).
final pendingStatusStoreProvider = Provider<PendingStatusStore>((ref) {
  return PendingStatusStore(ref.watch(sharedPreferencesProvider));
});

/// Bộ đồng bộ hàng đợi khi có mạng lại. Khởi động ở app shell sau khi đăng nhập.
final pendingStatusSyncProvider = Provider<PendingStatusSync>((ref) {
  final sync = PendingStatusSync(
    store: ref.watch(pendingStatusStoreProvider),
    repository: ref.watch(pickupRepositoryProvider),
    connectivity: ref.watch(connectivityServiceProvider),
  );
  ref.onDispose(sync.stop);
  return sync;
});

/// Client gọi proxy VietMap (routing) của Mobile API.
final vietmapApiProvider = Provider<VietmapApi>((ref) {
  return VietmapApi(ref.watch(dioClientProvider));
});

/// Service định vị (geolocator).
final locationServiceProvider = Provider<LocationService>((ref) {
  return const LocationService();
});

/// Controller màn hình bản đồ — theo điểm pickup ([LatLngPoint] đích).
final pickupRouteControllerProvider = StateNotifierProvider.autoDispose
    .family<PickupRouteController, PickupRouteState, LatLngPoint>(
  (ref, destination) {
    return PickupRouteController(
      vietmapApi: ref.watch(vietmapApiProvider),
      locationService: ref.watch(locationServiceProvider),
      destination: destination,
    );
  },
);
