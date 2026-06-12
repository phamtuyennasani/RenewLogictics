import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../data/ops_pickup_api.dart';
import '../data/ops_pickup_repository.dart';

/// DI cho feature ops_pickups.
final opsPickupApiProvider = Provider<OpsPickupApi>((ref) {
  return OpsPickupApi(ref.watch(dioClientProvider));
});

final opsPickupRepositoryProvider = Provider<OpsPickupRepository>((ref) {
  return OpsPickupRepository(ref.watch(opsPickupApiProvider));
});
