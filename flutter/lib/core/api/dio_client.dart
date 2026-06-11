import 'dart:io';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';

import '../../app/env.dart';
import '../storage/secure_token_storage.dart';
import 'api_envelope.dart';
import 'api_exception.dart';
import 'auth_interceptor.dart';

/// Bọc Dio cho toàn app: base URL `/api/mobile`, timeout, AuthInterceptor,
/// và parse envelope chuẩn (`success/message/data/errors`).
///
/// Mọi request đi qua [get]/[post] sẽ:
/// - Trả về [ApiEnvelope] khi `success = true`.
/// - Ném [ApiException] khi lỗi (HTTP error hoặc `success = false`).
class DioClient {
  DioClient({
    required SecureTokenStorage tokenStorage,
    required Future<void> Function() onUnauthorized,
    Dio? dio,
  }) : _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = Env.apiBase
      ..connectTimeout = Env.connectTimeout
      ..receiveTimeout = Env.receiveTimeout
      ..headers['Accept'] = 'application/json'
      // Tự xử lý status code thay vì để Dio ném ngay, để đọc được envelope.
      ..validateStatus = (status) => status != null && status < 500;

    // Dev: ép Host header để name-based vhost (MAMP) route đúng khi gọi qua IP.
    final hostHeader = Env.apiHostHeader;
    if (hostHeader != null) {
      _dio.options.headers['Host'] = hostHeader;
    }

    _dio.interceptors.add(
      AuthInterceptor(
        tokenStorage: tokenStorage,
        onUnauthorized: onUnauthorized,
      ),
    );

    // CHỈ cho dev với cert self-signed (logictics.local). Không bật ở prod.
    if (Env.allowBadCert) {
      final adapter = _dio.httpClientAdapter;
      if (adapter is IOHttpClientAdapter) {
        adapter.createHttpClient = () {
          final client = HttpClient();
          client.badCertificateCallback = (cert, host, port) => true;
          return client;
        };
      }
    }
  }

  final Dio _dio;

  Dio get raw => _dio;

  Future<ApiEnvelope> get(
    String path, {
    Map<String, dynamic>? query,
    bool skipAuth = false,
  }) {
    return _send(
      () => _dio.get(
        path,
        queryParameters: query,
        options: Options(extra: {'skipAuth': skipAuth}),
      ),
    );
  }

  Future<ApiEnvelope> post(
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    bool skipAuth = false,
  }) {
    return _send(
      () => _dio.post(
        path,
        data: body,
        queryParameters: query,
        options: Options(extra: {'skipAuth': skipAuth}),
      ),
    );
  }

  /// Gọi request, validate status + envelope, chuẩn hóa lỗi thành [ApiException].
  Future<ApiEnvelope> _send(Future<Response<dynamic>> Function() run) async {
    try {
      final response = await run();
      final status = response.statusCode ?? 0;
      final data = response.data;

      // validateStatus đã chặn >=500, nhưng phòng khi.
      final map = data is Map<String, dynamic> ? data : <String, dynamic>{};
      final envelope = ApiEnvelope.fromJson(map);

      // 2xx + success → trả envelope.
      if (status >= 200 && status < 300 && envelope.success) {
        return envelope;
      }

      // Còn lại: build ApiException từ status + envelope message/errors.
      throw ApiException(
        kind: _kindFromStatus(status),
        message: envelope.message.isNotEmpty
            ? envelope.message
            : _fallbackMessage(status),
        statusCode: status,
        errors: envelope.errors,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  static ApiErrorKind _kindFromStatus(int status) {
    switch (status) {
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
      default:
        return status >= 500 ? ApiErrorKind.server : ApiErrorKind.unknown;
    }
  }

  static String _fallbackMessage(int status) {
    switch (status) {
      case 401:
        return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
      case 403:
        return 'Bạn không có quyền thực hiện thao tác này.';
      case 404:
        return 'Không tìm thấy dữ liệu.';
      case 409:
        return 'Trạng thái đã thay đổi. Vui lòng tải lại.';
      case 422:
        return 'Dữ liệu không hợp lệ.';
      case 429:
        return 'Bạn thao tác quá nhanh. Vui lòng chờ một lát.';
      default:
        return 'Đã có lỗi xảy ra. Vui lòng thử lại.';
    }
  }
}
