import '../../../core/api/api_envelope.dart';
import '../../../core/api/dio_client.dart';

/// Gọi endpoint auth thô (contract §2). Trả [ApiEnvelope], không tự map domain.
class AuthApi {
  AuthApi(this._client);

  final DioClient _client;

  /// `POST /login` — public, không cần token.
  Future<ApiEnvelope> login({
    required String username,
    required String password,
    String? deviceName,
  }) {
    return _client.post(
      '/login',
      skipAuth: true,
      body: {
        'username': username,
        'password': password,
        if (deviceName != null && deviceName.isNotEmpty) 'device_name': deviceName,
      },
    );
  }

  /// `GET /me` — cần token.
  Future<ApiEnvelope> me() => _client.get('/me');

  /// `POST /logout` — revoke token hiện tại.
  Future<ApiEnvelope> logout() => _client.post('/logout');
}
