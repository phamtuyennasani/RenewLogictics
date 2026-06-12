import '../../shipper_pickup/domain/pickup.dart';
import 'ops_pickup_api.dart';

/// Repository cho OPS pickups (tái dùng model Pickup từ shipper_pickup).
class OpsPickupRepository {
  OpsPickupRepository(this._api);

  final OpsPickupApi _api;

  Future<OpsPickupListResult> list({
    String? tab,
    String? status,
    String? keyword,
    int page = 1,
    int perPage = 15,
  }) async {
    final envelope = await _api.list(
      tab: tab,
      status: status,
      keyword: keyword,
      page: page,
      perPage: perPage,
    );

    final data = envelope.data as Map<String, dynamic>? ?? {};
    final summary = PickupSummary.fromJson(
      data['summary'] as Map<String, dynamic>? ?? {},
    );

    final items = (data['items'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map(Pickup.fromJson)
            .toList() ??
        [];

    final meta = data['meta'] as Map<String, dynamic>? ?? {};

    return OpsPickupListResult(
      summary: summary,
      page: PickupPage(
        items: items,
        currentPage: (meta['current_page'] as num?)?.toInt() ?? page,
        perPage: (meta['per_page'] as num?)?.toInt() ?? perPage,
        total: (meta['total'] as num?)?.toInt() ?? 0,
        lastPage: (meta['last_page'] as num?)?.toInt() ?? 1,
        hasMore: meta['has_more'] == true,
      ),
    );
  }

  Future<PickupDetail> detail(int pickupId) async {
    final envelope = await _api.detail(pickupId);
    final data = envelope.data as Map<String, dynamic>? ?? {};
    return PickupDetail.fromJson(data);
  }

  Future<void> assignShipper(int pickupId, int shipperId) async {
    await _api.assignShipper(pickupId: pickupId, shipperId: shipperId);
  }

  Future<List<ShipperOption>> shippers() async {
    final envelope = await _api.shippers();
    final data = envelope.data as Map<String, dynamic>? ?? {};
    final shippers = (data['shippers'] as List?)
            ?.whereType<Map<String, dynamic>>()
            .map((s) => ShipperOption(
                  id: (s['id'] as num?)?.toInt() ?? 0,
                  name: (s['name'] ?? '').toString(),
                ))
            .toList() ??
        [];
    return shippers;
  }
}

/// Kết quả list pickup (tái dùng từ shipper pattern).
class OpsPickupListResult {
  const OpsPickupListResult({required this.summary, required this.page});

  final PickupSummary summary;
  final PickupPage page;
}

class PickupPage {
  const PickupPage({
    required this.items,
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasMore,
  });

  final List<Pickup> items;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;
}

class PickupSummary {
  const PickupSummary({this.pendingCount = 0, this.nearestScheduleAt});

  final int pendingCount;
  final DateTime? nearestScheduleAt;

  factory PickupSummary.fromJson(Map<String, dynamic> json) {
    return PickupSummary(
      pendingCount: (json['pending_count'] as num?)?.toInt() ?? 0,
      nearestScheduleAt: _parseDate(json['nearest_schedule_at']),
    );
  }

  static DateTime? _parseDate(Object? raw) {
    if (raw == null) return null;
    return DateTime.tryParse(raw.toString());
  }
}

class ShipperOption {
  const ShipperOption({required this.id, required this.name});

  final int id;
  final String name;
}
