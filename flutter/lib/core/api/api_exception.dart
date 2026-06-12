import 'package:dio/dio.dart';

import 'api_envelope.dart';

/// Phân loại lỗi API để UI hiển thị message tiếng Việt theo contract §1.4.
enum ApiErrorKind {
  network, // mất mạng / timeout
  badRequest, // 400 — request sai format / proxy trả lỗi
  unauthorized, // 401 — token hỏng, cần logout
  forbidden, // 403 — sai quyền/role
  notFound, // 404
  conflict, // 409 — sai FSM / đã xử lý
  validation, // 422 — kèm errors theo field
  rateLimited, // 429
  server, // 500
  unknown,
}

/// Exception thống nhất cho toàn app. Repository bắt DioException và map sang đây.
class ApiException implements Exception {
  ApiException({
    required this.kind,
    required this.message,
    this.statusCode,
    this.errors,
  });

  final ApiErrorKind kind;
  final String message;
  final int? statusCode;
  final Map<String, List<String>>? errors;

  bool get isUnauthorized => kind == ApiErrorKind.unauthorized;

  @override
  String toString() => 'ApiException($kind, $statusCode): $message';

  /// Build từ một DioException — đọc envelope `message`/`errors` nếu có.
  factory ApiException.fromDio(DioException e) {
    // Lỗi tầng kết nối (không có response).
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return ApiException(
          kind: ApiErrorKind.network,
          message: 'Kết nối quá chậm. Vui lòng thử lại.',
        );
      case DioExceptionType.connectionError:
        return ApiException(
          kind: ApiErrorKind.network,
          message: 'Không có kết nối mạng. Vui lòng kiểm tra và thử lại.',
        );
      case DioExceptionType.cancel:
        return ApiException(
          kind: ApiErrorKind.unknown,
          message: 'Yêu cầu đã bị hủy.',
        );
      default:
        break;
    }

    final response = e.response;
    final status = response?.statusCode;

    // Cố gắng đọc envelope từ body.
    String message = 'Đã có lỗi xảy ra. Vui lòng thử lại.';
    Map<String, List<String>>? errors;
    final data = response?.data;
    if (data is Map<String, dynamic>) {
      final env = ApiEnvelope.fromJson(data);
      if (env.message.isNotEmpty) message = env.message;
      errors = env.errors;
    }

    final kind = _kindFromStatus(status);
    // Message mặc định theo status nếu body không có message hữu ích.
    if (data is! Map || (data['message'] == null)) {
      message = _defaultMessageFor(kind);
    }

    return ApiException(
      kind: kind,
      message: message,
      statusCode: status,
      errors: errors,
    );
  }

  static ApiErrorKind _kindFromStatus(int? status) {
    switch (status) {
      case 400:
        return ApiErrorKind.badRequest;
      case 401:
        return ApiErrorKind.unauthorized;
      case 403:
        return ApiErrorKind.forbidden;
      case 404:
        return ApiErrorKind.notFound;
      case 409:
        return ApiErrorKind.conflict;
      case 422:
        return ApiErrorKind.validation;
      case 429:
        return ApiErrorKind.rateLimited;
      case 500:
      case 502:
      case 503:
        return ApiErrorKind.server;
      default:
        return ApiErrorKind.unknown;
    }
  }

  static String _defaultMessageFor(ApiErrorKind kind) {
    switch (kind) {
      case ApiErrorKind.unauthorized:
        return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
      case ApiErrorKind.forbidden:
        return 'Bạn không có quyền thực hiện thao tác này.';
      case ApiErrorKind.notFound:
        return 'Không tìm thấy dữ liệu.';
      case ApiErrorKind.conflict:
        return 'Trạng thái đã thay đổi. Vui lòng tải lại.';
      case ApiErrorKind.validation:
        return 'Dữ liệu không hợp lệ.';
      case ApiErrorKind.rateLimited:
        return 'Bạn thao tác quá nhanh. Vui lòng chờ một lát.';
      case ApiErrorKind.server:
        return 'Lỗi hệ thống. Vui lòng thử lại sau.';
      case ApiErrorKind.network:
        return 'Không có kết nối mạng. Vui lòng thử lại.';
      case ApiErrorKind.badRequest:
        return 'Yêu cầu không hợp lệ. Vui lòng kiểm tra cấu hình API.';
      case ApiErrorKind.unknown:
        return 'Đã có lỗi xảy ra. Vui lòng thử lại.';
    }
  }
}
