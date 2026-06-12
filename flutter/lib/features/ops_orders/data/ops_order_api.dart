import '../../../core/api/api_envelope.dart';
import '../../../core/api/dio_client.dart';

/// Gọi endpoint OPS orders thô. Trả [ApiEnvelope].
class OpsOrderApi {
  OpsOrderApi(this._client);

  final DioClient _client;

  /// `GET /ops/orders`
  Future<ApiEnvelope> list({
    String? keyword,
    String? status,
    bool? hasPickup,
    int page = 1,
    int perPage = 15,
  }) {
    return _client.get(
      '/ops/orders',
      query: {
        if (keyword != null && keyword.isNotEmpty) 'keyword': keyword,
        if (status != null && status.isNotEmpty) 'status': status,
        if (hasPickup != null) 'has_pickup': hasPickup,
        'page': page,
        'per_page': perPage,
      },
    );
  }

  /// `GET /ops/orders/{id}`
  Future<ApiEnvelope> detail(int orderId) {
    return _client.get('/ops/orders/$orderId');
  }

  /// `POST /ops/orders/{id}/pickups`
  Future<ApiEnvelope> createPickup(int orderId, Map<String, dynamic> data) {
    return _client.post('/ops/orders/$orderId/pickups', body: data);
  }
}
