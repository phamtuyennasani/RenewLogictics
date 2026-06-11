import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api/dio_client.dart';
import 'storage/secure_token_storage.dart';

/// Provider hạ tầng dùng chung toàn app (DI gốc).

/// Secure storage cho token.
final tokenStorageProvider = Provider<SecureTokenStorage>((ref) {
  return SecureTokenStorage();
});

/// SharedPreferences instance — override ở main() sau khi `getInstance()`.
///
/// Dùng cho dữ liệu không nhạy cảm (lịch sử scan trong phiên, cài đặt UI).
/// KHÔNG lưu token ở đây — token chỉ ở secure storage.
final sharedPreferencesProvider = Provider<SharedPreferences>((ref) {
  throw UnimplementedError(
    'sharedPreferencesProvider phải được override trong main() '
    'bằng giá trị từ SharedPreferences.getInstance().',
  );
});

/// Cờ phát tín hiệu khi token bị 401 (token hỏng/hết hạn).
///
/// DioClient không thể phụ thuộc trực tiếp authControllerProvider (vòng lặp),
/// nên nó set cờ này; router/listener phản ứng để điều hướng về Login.
final unauthorizedSignalProvider = StateProvider<int>((ref) => 0);

/// DioClient dùng chung. Khi gặp 401, tăng [unauthorizedSignalProvider].
final dioClientProvider = Provider<DioClient>((ref) {
  final storage = ref.watch(tokenStorageProvider);
  return DioClient(
    tokenStorage: storage,
    onUnauthorized: () async {
      // Token đã được AuthInterceptor xóa; chỉ cần báo hiệu cho UI.
      ref.read(unauthorizedSignalProvider.notifier).state++;
    },
  );
});
