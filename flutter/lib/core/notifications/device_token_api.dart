import '../api/api_envelope.dart';
import '../api/dio_client.dart';

/// Gọi endpoint đăng ký / thu hồi FCM device token (push notification).
/// Khớp backend: POST /device-tokens, POST /device-tokens/revoke.
class DeviceTokenApi {
  DeviceTokenApi(this._client);

  final DioClient _client;

  /// Upsert token theo fcm_token, gắn user hiện tại.
  Future<ApiEnvelope> register({
    required String fcmToken,
    String? platform,
    String? deviceName,
    String? appVersion,
  }) {
    return _client.post(
      '/device-tokens',
      body: {
        'fcm_token': fcmToken,
        'platform': ?platform,
        'device_name': ?deviceName,
        'app_version': ?appVersion,
      },
    );
  }

  /// Thu hồi token (gọi trước khi logout).
  Future<ApiEnvelope> revoke(String fcmToken) {
    return _client.post(
      '/device-tokens/revoke',
      body: {'fcm_token': fcmToken},
    );
  }
}
