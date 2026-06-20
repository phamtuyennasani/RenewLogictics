import '../../../core/api/api_envelope.dart';
import '../../../core/api/dio_client.dart';

class NotificationsApi {
  const NotificationsApi(this._client);

  final DioClient _client;

  Future<ApiEnvelope> list({int page = 1, int perPage = 30}) {
    return _client.get(
      '/notifications',
      query: {'page': page, 'per_page': perPage},
    );
  }

  Future<ApiEnvelope> show(int id) {
    return _client.get('/notifications/$id');
  }

  Future<ApiEnvelope> markRead(int id) {
    return _client.post('/notifications/$id/read');
  }

  Future<ApiEnvelope> markAllRead() {
    return _client.post('/notifications/read-all');
  }
}
