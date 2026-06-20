import 'package:dio/dio.dart';

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
        if (deviceName != null && deviceName.isNotEmpty)
          'device_name': deviceName,
      },
    );
  }

  /// `GET /me` — cần token.
  Future<ApiEnvelope> me() => _client.get('/me');

  /// `POST /logout` — revoke token hiện tại.
  Future<ApiEnvelope> logout() => _client.post('/logout');

  /// `PUT /profile` — cập nhật thông tin cá nhân.
  Future<ApiEnvelope> updateProfile({
    required String fullname,
    String? email,
    String? phone,
    String? address,
  }) {
    return _client.put(
      '/profile',
      body: {
        'fullname': fullname,
        'email': email,
        'phone': phone,
        'address': address,
      },
    );
  }

  /// `PUT /profile/password` — đổi mật khẩu.
  Future<ApiEnvelope> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) {
    return _client.put(
      '/profile/password',
      body: {
        'current_password': currentPassword,
        'new_password': newPassword,
        'confirm_password': confirmPassword,
      },
    );
  }

  /// `POST /profile/avatar` — upload ảnh đại diện (multipart).
  Future<ApiEnvelope> uploadAvatar(String filePath) async {
    // Tách tên file an toàn trên cả `/` (mobile) lẫn `\` (phòng desktop).
    final fileName = filePath.split(RegExp(r'[/\\]')).last;
    final formData = FormData.fromMap({
      'avatar': await MultipartFile.fromFile(filePath, filename: fileName),
    });
    return _client.postMultipart('/profile/avatar', formData: formData);
  }
}
