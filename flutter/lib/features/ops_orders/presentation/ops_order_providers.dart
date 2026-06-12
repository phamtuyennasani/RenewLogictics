import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../data/ops_common_api.dart';
import '../data/ops_order_api.dart';
import '../data/ops_order_repository_impl.dart';
import '../domain/ops_order_repository.dart';

/// DI cho feature ops_orders.
final opsOrderApiProvider = Provider<OpsOrderApi>((ref) {
  return OpsOrderApi(ref.watch(dioClientProvider));
});

final opsOrderRepositoryProvider = Provider<OpsOrderRepository>((ref) {
  return OpsOrderRepositoryImpl(ref.watch(opsOrderApiProvider));
});

final opsCommonApiProvider = Provider<OpsCommonApi>((ref) {
  return OpsCommonApi(ref.watch(dioClientProvider));
});
