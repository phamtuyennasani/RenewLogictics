import '../../../core/api/api_envelope.dart';
import '../../../core/api/dio_client.dart';

/// Gọi endpoint shipper pickup thô (contract §3). Trả [ApiEnvelope].
class PickupApi {
  PickupApi(this._client);

  final DioClient _client;

  /// `GET /shipper/pickups`
  Future<ApiEnvelope> list({
    String? tab,
    String? status,
    String? keyword,
    int page = 1,
    int perPage = 15,
  }) {
    return _client.get(
      '/shipper/pickups',
      query: {
        if (tab != null && tab.isNotEmpty) 'tab': tab,
        if (status != null && status.isNotEmpty) 'status': status,
        if (keyword != null && keyword.isNotEmpty) 'keyword': keyword,
        'page': page,
        'per_page': perPage,
      },
    );
  }

  /// `GET /shipper/pickups/{id}`
  Future<ApiEnvelope> detail(int pickupId) {
    return _client.get('/shipper/pickups/$pickupId');
  }

  /// `POST /shipper/pickups/{id}/status`
  Future<ApiEnvelope> updateStatus({
    required int pickupId,
    required String status,
    String? reason,
    double? lat,
    double? lng,
  }) {
    return _client.post(
      '/shipper/pickups/$pickupId/status',
      body: {
        'status': status,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
        'lat': ?lat,
        'lng': ?lng,
      },
    );
  }
}
