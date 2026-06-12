import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/location/location_service.dart';
import '../../../core/providers.dart';
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
