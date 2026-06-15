import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/notifications/push_providers.dart';
import '../../../core/providers.dart';
import '../../../core/storage/secure_token_storage.dart';
import '../data/auth_api.dart';
import '../data/auth_repository_impl.dart';
import '../domain/auth_repository.dart';
import '../domain/user_session.dart';

/// DI cho feature auth.
final authApiProvider = Provider<AuthApi>((ref) {
  return AuthApi(ref.watch(dioClientProvider));
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepositoryImpl(
    api: ref.watch(authApiProvider),
    tokenStorage: ref.watch(tokenStorageProvider),
  );
});

/// Trạng thái xác thực của app.
enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  const AuthState({
    required this.status,
    this.session,
    this.errorMessage,
    this.isSubmitting = false,
  });

  final AuthStatus status;
  final UserSession? session;
  final String? errorMessage;
  final bool isSubmitting;

  const AuthState.unknown()
    : status = AuthStatus.unknown,
      session = null,
      errorMessage = null,
      isSubmitting = false;

  AuthState copyWith({
    AuthStatus? status,
    UserSession? session,
    String? errorMessage,
    bool clearError = false,
    bool? isSubmitting,
  }) {
    return AuthState(
      status: status ?? this.status,
      session: session ?? this.session,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      isSubmitting: isSubmitting ?? this.isSubmitting,
    );
  }

  bool get isAuthenticated => status == AuthStatus.authenticated;
}

/// Notifier quản lý phiên đăng nhập. Router lắng nghe để điều hướng.
class AuthController extends Notifier<AuthState> {
  AuthRepository get _repo => ref.read(authRepositoryProvider);

  /// Thông báo khi role không được phép dùng app (chỉ shipper & OPS).
  static const _roleNotAllowedMessage =
      'Tài khoản của bạn không có quyền sử dụng ứng dụng này. '
      'Ứng dụng chỉ dành cho nhân viên Shipper và OPS.';

  @override
  AuthState build() {
    // Khi nhận tín hiệu 401 từ DioClient, đẩy về unauthenticated.
    ref.listen(unauthorizedSignalProvider, (previous, next) {
      if (previous != next && state.status == AuthStatus.authenticated) {
        state = const AuthState(
          status: AuthStatus.unauthenticated,
          errorMessage: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
        );
      }
    });
    return const AuthState.unknown();
  }

  /// Khôi phục phiên lúc khởi động: có token → gọi /me.
  Future<void> restoreSession() async {
    final storage = ref.read(tokenStorageProvider);
    final token = await storage.read();
    if (token == null || token.isEmpty) {
      state = const AuthState(status: AuthStatus.unauthenticated);
      return;
    }

    final biometric = await ref.read(biometricAuthProvider).availability();
    if (biometric.canAuthenticate) {
      state = const AuthState(status: AuthStatus.unauthenticated);
      return;
    }

    await _restoreStoredSession(storage);
  }

  Future<void> unlockWithBiometrics() async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    final storage = ref.read(tokenStorageProvider);
    final token = await storage.read();
    if (token == null || token.isEmpty) {
      state = const AuthState(
        status: AuthStatus.unauthenticated,
        errorMessage:
            'Chưa có phiên đăng nhập để mở khóa bằng Face ID/Touch ID.',
      );
      return;
    }

    final ok = await ref
        .read(biometricAuthProvider)
        .authenticate(
          reason: 'Xác thực để mở khóa phiên đăng nhập Bee Express.',
        );
    if (!ok) {
      state = state.copyWith(isSubmitting: false);
      return;
    }

    await _restoreStoredSession(storage);
  }

  Future<void> _restoreStoredSession(SecureTokenStorage storage) async {
    try {
      final session = await _repo.me();
      if (!session.canUseApp) {
        await _rejectDisallowedRole(storage);
        return;
      }
      state = AuthState(status: AuthStatus.authenticated, session: session);
      _registerPushDevice();
    } catch (_) {
      // Token hỏng/hết hạn → xóa và yêu cầu đăng nhập lại.
      await storage.clear();
      state = const AuthState(status: AuthStatus.unauthenticated);
    }
  }

  /// Role không thuộc shipper/OPS: thu hồi token đã lưu và báo lỗi.
  Future<void> _rejectDisallowedRole(SecureTokenStorage storage) async {
    try {
      await _repo.logout();
    } catch (_) {
      await storage.clear();
    }
    state = const AuthState(
      status: AuthStatus.unauthenticated,
      errorMessage: _roleNotAllowedMessage,
    );
  }

  Future<void> login({
    required String username,
    required String password,
    String? deviceName,
  }) async {
    state = state.copyWith(isSubmitting: true, clearError: true);
    try {
      final result = await _repo.login(
        username: username,
        password: password,
        deviceName: deviceName,
      );
      if (!result.session.canUseApp) {
        await _rejectDisallowedRole(ref.read(tokenStorageProvider));
        return;
      }
      state = AuthState(
        status: AuthStatus.authenticated,
        session: result.session,
      );
      _registerPushDevice();
    } on Object catch (e) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: _messageOf(e),
        status: AuthStatus.unauthenticated,
      );
    }
  }

  Future<void> logout() async {
    // Thu hồi device token trước khi mất Bearer (server cần auth để revoke).
    await ref.read(pushRegistrationProvider).revokeCurrentDevice();
    try {
      await _repo.logout();
    } catch (_) {
      // Logout server lỗi vẫn xóa local; bỏ qua.
    }
    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  /// Đăng ký FCM token lên server (fire-and-forget, không chặn UI).
  void _registerPushDevice() {
    ref.read(pushRegistrationProvider).registerCurrentDevice();
  }

  void clearError() {
    if (state.errorMessage != null) {
      state = state.copyWith(clearError: true);
    }
  }

  static String _messageOf(Object e) {
    final msg = e.toString();
    return msg.isEmpty ? 'Đăng nhập thất bại.' : _extract(e);
  }

  static String _extract(Object e) {
    // ApiException.toString có prefix; ưu tiên .message nếu có.
    try {
      final dynamic dyn = e;
      final m = dyn.message;
      if (m is String && m.isNotEmpty) return m;
    } catch (_) {}
    return 'Đăng nhập thất bại. Vui lòng thử lại.';
  }
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);
