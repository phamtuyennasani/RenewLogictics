import '../../../core/models/paginated.dart';
import '../domain/ops_order.dart';
import '../domain/ops_order_repository.dart';
import 'ops_order_api.dart';

class OpsOrderRepositoryImpl implements OpsOrderRepository {
  OpsOrderRepositoryImpl(this._api);

  final OpsOrderApi _api;

  @override
  Future<Paginated<OpsOrder>> list({
    String? keyword,
    String? status,
    bool? hasPickup,
    int page = 1,
    int perPage = 15,
  }) async {
    final envelope = await _api.list(
      keyword: keyword,
      status: status,
      hasPickup: hasPickup,
      page: page,
      perPage: perPage,
    );

    final data = envelope.data as Map<String, dynamic>? ?? {};
    final items = (data['items'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(OpsOrder.fromJson)
            .toList() ??
        [];

    final meta = data['meta'] as Map<String, dynamic>? ?? {};

    return Paginated(
      items: items,
      currentPage: (meta['current_page'] as num?)?.toInt() ?? page,
      perPage: (meta['per_page'] as num?)?.toInt() ?? perPage,
      total: (meta['total'] as num?)?.toInt() ?? 0,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? 1,
      hasMore: meta['has_more'] == true,
    );
  }

  @override
  Future<OpsOrderDetail> detail(int orderId) async {
    final envelope = await _api.detail(orderId);
    final data = envelope.data as Map<String, dynamic>? ?? {};
    return OpsOrderDetail.fromJson(data);
  }

  @override
  Future<Map<String, dynamic>> createPickup(
      int orderId, Map<String, dynamic> data) async {
    final envelope = await _api.createPickup(orderId, data);
    return envelope.data as Map<String, dynamic>? ?? {};
  }
}
