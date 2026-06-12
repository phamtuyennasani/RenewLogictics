import '../../../core/api/api_envelope.dart';
import '../../../core/api/dio_client.dart';

/// Gọi endpoint OPS pickups thô. Trả [ApiEnvelope].
class OpsPickupApi {
  OpsPickupApi(this._client);

  final DioClient _client;

  /// `GET /ops/pickups`
  Future<ApiEnvelope> list({
    String? tab,
    String? status,
    String? keyword,
    int page = 1,
    int perPage = 15,
  }) {
    return _client.get(
      '/ops/pickups',
      query: {
        if (tab != null && tab.isNotEmpty) 'tab': tab,
        if (status != null && status.isNotEmpty) 'status': status,
        if (keyword != null && keyword.isNotEmpty) 'keyword': keyword,
        'page': page,
        'per_page': perPage,
      },
    );
  }

  /// `GET /ops/pickups/{id}`
  Future<ApiEnvelope> detail(int pickupId) {
    return _client.get('/ops/pickups/$pickupId');
  }

  /// `POST /ops/pickups/{id}/assign-shipper`
  Future<ApiEnvelope> assignShipper({
    required int pickupId,
    required int shipperId,
  }) {
    return _client.post(
      '/ops/pickups/$pickupId/assign-shipper',
      body: {'shipper_id': shipperId},
    );
  }

  /// `GET /ops/shippers`
  Future<ApiEnvelope> shippers() {
    return _client.get('/ops/shippers');
  }
}
