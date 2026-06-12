import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../data/recent_pickup_scans_store.dart';
import '../data/shipper_scan_api.dart';
import '../data/shipper_scan_repository_impl.dart';
import '../domain/shipper_scan_repository.dart';

/// DI cho feature shipper scan.
final shipperScanApiProvider = Provider<ShipperScanApi>((ref) {
  return ShipperScanApi(ref.watch(dioClientProvider));
});

final shipperScanRepositoryProvider = Provider<ShipperScanRepository>((ref) {
  return ShipperScanRepositoryImpl(ref.watch(shipperScanApiProvider));
});

/// Store lịch sử quét nhận pickup trong phiên (local qua SharedPreferences).
final recentPickupScansStoreProvider = Provider<RecentPickupScansStore>((ref) {
  return RecentPickupScansStore(ref.watch(sharedPreferencesProvider));
});
