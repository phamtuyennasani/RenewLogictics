import '../../../core/storage/secure_token_storage.dart';
import '../domain/auth_repository.dart';
import '../domain/user_session.dart';
import 'auth_api.dart';

/// Triển khai [AuthRepository]: gọi [AuthApi], map envelope → domain,
/// và quản lý lưu/xóa token trong secure storage.
class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl({
    required AuthApi api,
    required SecureTokenStorage tokenStorage,
  })  : _api = api,
        _tokenStorage = tokenStorage;

  final AuthApi _api;
  final SecureTokenStorage _tokenStorage;

  @override
  Future<LoginResult> login({
    required String username,
    required String password,
    String? deviceName,
  }) async {
    final env = await _api.login(
      username: username,
      password: password,
      deviceName: deviceName,
    );

    final data = env.dataMap;
    final token = (data['token'] ?? '').toString();
    final session = UserSession.fromData(data);

    // Lưu token ngay để các request sau gắn Bearer được.
    if (token.isNotEmpty) {
      await _tokenStorage.write(token);
    }

    return LoginResult(session: session, token: token);
  }

  @override
  Future<UserSession> me() async {
    final env = await _api.me();
    return UserSession.fromData(env.dataMap);
  }

  @override
  Future<void> logout() async {
    try {
      await _api.logout();
    } finally {
      // Luôn xóa token local dù server có lỗi.
      await _tokenStorage.clear();
    }
  }
}
