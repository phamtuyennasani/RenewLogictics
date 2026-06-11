import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../data/pickup_api.dart';
import '../data/pickup_repository_impl.dart';
import '../domain/pickup_repository.dart';

/// DI cho feature shipper pickup.
final pickupApiProvider = Provider<PickupApi>((ref) {
  return PickupApi(ref.watch(dioClientProvider));
});

final pickupRepositoryProvider = Provider<PickupRepository>((ref) {
  return PickupRepositoryImpl(ref.watch(pickupApiProvider));
});
