import '../../../core/api/dio_client.dart';

/// Helper API cho locations (provinces, wards) và shippers.
class OpsCommonApi {
  OpsCommonApi(this._client);

  final DioClient _client;

  /// `GET /ops/locations/provinces`
  Future<List<Map<String, dynamic>>> provinces() async {
    final envelope = await _client.get('/ops/locations/provinces');
    final data = envelope.data as Map<String, dynamic>? ?? {};
    final provinces = data['provinces'] as List? ?? [];
    return provinces.whereType<Map<String, dynamic>>().toList();
  }

  /// `GET /ops/locations/wards?province_id={id}`
  Future<List<Map<String, dynamic>>> wards(int provinceId) async {
    final envelope = await _client.get(
      '/ops/locations/wards',
      query: {'province_id': provinceId},
    );
    final data = envelope.data as Map<String, dynamic>? ?? {};
    final wards = data['wards'] as List? ?? [];
    return wards.whereType<Map<String, dynamic>>().toList();
  }

  /// `GET /ops/shippers`
  Future<List<Map<String, dynamic>>> shippers() async {
    final envelope = await _client.get('/ops/shippers');
    final data = envelope.data as Map<String, dynamic>? ?? {};
    final shippers = data['shippers'] as List? ?? [];
    return shippers.whereType<Map<String, dynamic>>().toList();
  }
}
