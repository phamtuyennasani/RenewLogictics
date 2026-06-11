import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../data/recent_scans_store.dart';
import '../data/scan_api.dart';
import '../data/scan_repository_impl.dart';
import '../domain/scan_repository.dart';

/// DI cho feature OPS scan.
final scanApiProvider = Provider<ScanApi>((ref) {
  return ScanApi(ref.watch(dioClientProvider));
});

final scanRepositoryProvider = Provider<ScanRepository>((ref) {
  return ScanRepositoryImpl(ref.watch(scanApiProvider));
});

/// Store lịch sử scan trong phiên (lưu local qua SharedPreferences).
final recentScansStoreProvider = Provider<RecentScansStore>((ref) {
  return RecentScansStore(ref.watch(sharedPreferencesProvider));
});
