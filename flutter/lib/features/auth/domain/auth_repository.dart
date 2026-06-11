import 'user_session.dart';

/// Kết quả login: session + token (token để app lưu vào secure storage).
class LoginResult {
  const LoginResult({required this.session, required this.token});

  final UserSession session;
  final String token;
}

/// Contract auth — presentation chỉ phụ thuộc abstraction này.
abstract class AuthRepository {
  /// Đăng nhập, trả session + token. Ném [ApiException] khi lỗi.
  Future<LoginResult> login({
    required String username,
    required String password,
    String? deviceName,
  });

  /// Lấy session hiện tại từ `/me` (xác thực token còn hiệu lực).
  Future<UserSession> me();

  /// Revoke token hiện tại phía server.
  Future<void> logout();
}
