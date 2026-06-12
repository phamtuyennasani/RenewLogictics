import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';

/// Cấu hình môi trường, đọc từ file `.env` (nạp ở [main]).
///
/// Mặc định trỏ về dev host của backend Laravel. KHÔNG hardcode token/secret ở đây.
class Env {
  const Env._();

  /// Base URL backend, ví dụ `https://logictics.local`.
  static String get apiBaseUrl =>
      _trimTrailingSlash(_get('API_BASE_URL') ?? 'https://logictics.local');

  static Uri get apiBaseUri => Uri.parse(apiBaseUrl);

  static String get apiHost => apiBaseUri.host;

  /// Prefix cố định theo contract: `/api/mobile`.
  static String get apiPrefix => '/api/mobile';

  /// Base đầy đủ cho Dio.
  static String get apiBase => '$apiBaseUrl$apiPrefix';

  /// Chỉ có hiệu lực khi debug + backend local. Nếu sau này đổi
  /// [apiBaseUrl] sang host thật, cờ dev này tự tắt để không bỏ qua TLS.
  static bool get allowBadCert =>
      kDebugMode &&
      isLocalBackend &&
      (_get('DEV_ALLOW_BAD_CERT') ?? 'false').toLowerCase() == 'true';

  /// DEV Android emulator: giữ URL/SNI là `logictics.local`, nhưng đổi TCP
  /// endpoint sang IP máy Mac. Đặt `auto` để dùng `10.0.2.2`; iOS simulator
  /// và macOS dùng DNS/hosts của máy Mac nên không cần.
  static String? get devResolveIp {
    if (!kDebugMode || !isLocalBackend) return null;
    final v = _get('DEV_RESOLVE_IP')?.trim();
    return (v == null || v.isEmpty) ? null : v;
  }

  /// Backend local/dev qua MAMP, simulator hoặc localhost.
  static bool get isLocalBackend =>
      apiHost == 'localhost' ||
      apiHost == '127.0.0.1' ||
      apiHost == '::1' ||
      apiHost == '10.0.2.2' ||
      apiHost.endsWith('.local');

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
