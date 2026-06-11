import 'package:flutter_dotenv/flutter_dotenv.dart';

/// Cấu hình môi trường, đọc từ file `.env` (nạp ở [main]).
///
/// Mặc định trỏ về dev host của backend Laravel. KHÔNG hardcode token/secret ở đây.
class Env {
  const Env._();

  /// Base URL backend, ví dụ `https://logictics.local`.
  static String get apiBaseUrl =>
      _trimTrailingSlash(_get('API_BASE_URL') ?? 'https://logictics.local');

  /// Prefix cố định theo contract: `/api/mobile`.
  static String get apiPrefix => '/api/mobile';

  /// Base đầy đủ cho Dio.
  static String get apiBase => '$apiBaseUrl$apiPrefix';

  /// CHỈ dùng cho dev với cert self-signed. KHÔNG bật ở production.
  static bool get allowBadCert =>
      (_get('DEV_ALLOW_BAD_CERT') ?? 'false').toLowerCase() == 'true';

  /// Ép HTTP Host header khi backend là name-based vhost (vd MAMP) mà phải
  /// gọi qua IP. Trả null nếu không cấu hình. KHÔNG dùng ở production.
  static String? get apiHostHeader {
    final v = _get('API_HOST_HEADER')?.trim();
    return (v == null || v.isEmpty) ? null : v;
  }

  static Duration get connectTimeout => const Duration(seconds: 15);
  static Duration get receiveTimeout => const Duration(seconds: 20);

  /// Đọc biến môi trường an toàn: nếu dotenv chưa init (load lỗi/thiếu file),
  /// trả null thay vì ném [NotInitializedError] làm crash app lúc khởi động.
  static String? _get(String key) {
    if (!dotenv.isInitialized) return null;
    return dotenv.maybeGet(key);
  }

  static String _trimTrailingSlash(String value) =>
      value.endsWith('/') ? value.substring(0, value.length - 1) : value;
}
