import 'dart:io';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';
import 'package:flutter/foundation.dart';

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
      ..contentType = Headers.jsonContentType
      ..responseType = ResponseType.json
      ..headers['Accept'] = 'application/json'
      // Tự xử lý status code thay vì để Dio ném ngay, để đọc được envelope.
      ..validateStatus = (status) => status != null && status < 500;

    if (kDebugMode) {
      _dio.interceptors.add(_debugInterceptor());
    }

    _dio.interceptors.add(
      AuthInterceptor(
        tokenStorage: tokenStorage,
        onUnauthorized: onUnauthorized,
      ),
    );

    _configureHttpClient();
  }

  /// Cấu hình tầng IO của Dio cho dev: bỏ qua cert self-signed khi backend local.
  /// Ở production cờ này tự tắt nên dùng DNS + cert thật, không cần sửa code.
  void _configureHttpClient() {
    final allowBadCert = Env.allowBadCert;
    final connectUri = _devConnectUriForCurrentPlatform();
    if (!allowBadCert && connectUri == null) return;

    final adapter = _dio.httpClientAdapter;
    if (adapter is! IOHttpClientAdapter) return;

    adapter.createHttpClient = () {
      final client = HttpClient();
      if (allowBadCert) {
        client.badCertificateCallback = (cert, host, port) => true;
      }
      if (connectUri != null) {
        client.connectionFactory = (uri, proxyHost, proxyPort) async {
          if (proxyHost != null && proxyPort != null) {
            return Socket.startConnect(proxyHost, proxyPort);
          }

          final port = connectUri.hasPort
              ? connectUri.port
              : (uri.hasPort ? uri.port : (uri.scheme == 'https' ? 443 : 80));
          final task = await Socket.startConnect(connectUri.host, port);
          if (uri.scheme != 'https') return task;

          final secureSocket = task.socket.then<Socket>(
            (socket) => SecureSocket.secure(
              socket,
              host: uri.host,
              onBadCertificate: allowBadCert ? (_) => true : null,
            ),
          );

          return ConnectionTask.fromSocket<Socket>(secureSocket, task.cancel);
        };
      }
      return client;
    };
  }

  final Dio _dio;

  Dio get raw => _dio;

  static Uri? _devConnectUriForCurrentPlatform() {
    final configured = Env.devResolveIp;
    if (defaultTargetPlatform != TargetPlatform.android || configured == null) {
      return null;
    }

    final endpoint = configured.toLowerCase() == 'auto'
        ? '10.0.2.2'
        : configured;
    try {
      if (endpoint.contains('://')) {
        return Uri.parse(endpoint);
      }

      final parts = endpoint.split(':');
      final host = parts.first;
      final port = parts.length == 2 ? int.tryParse(parts.last) : null;
      return Env.apiBaseUri.replace(host: host, port: port);
    } catch (_) {
      return null;
    }
  }

  Interceptor _debugInterceptor() {
    return InterceptorsWrapper(
      onRequest: (options, handler) {
        debugPrint(
          '[API] → ${options.method} ${options.uri} body=${_sanitize(options.data)}',
        );
        handler.next(options);
      },
      onResponse: (response, handler) {
        final status = response.statusCode ?? 0;
        if (status >= 400) {
          debugPrint(
            '[API] ← $status ${response.requestOptions.uri} body=${_sanitize(response.data)}',
          );
        }
        handler.next(response);
      },
      onError: (error, handler) {
        debugPrint(
          '[API] ✕ ${error.response?.statusCode ?? error.type} '
          '${error.requestOptions.uri} body=${_sanitize(error.response?.data)}',
        );
        handler.next(error);
      },
    );
  }

  static Object? _sanitize(Object? value) {
    if (value is Map) {
      return value.map((key, child) {
        final name = key.toString().toLowerCase();
        if (name.contains('password') ||
            name.contains('token') ||
            name == 'authorization') {
          return MapEntry(key, '***');
        }
        return MapEntry(key, _sanitize(child));
      });
    }
    if (value is List) {
      return value.map(_sanitize).toList(growable: false);
    }
    return value;
  }

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

  Future<ApiEnvelope> put(
    String path, {
    Object? body,
    Map<String, dynamic>? query,
    bool skipAuth = false,
  }) {
    return _send(
      () => _dio.put(
        path,
        data: body,
        queryParameters: query,
        options: Options(extra: {'skipAuth': skipAuth}),
      ),
    );
  }

  /// Gửi multipart (upload file). Ghi đè contentType để Dio tự set
  /// `multipart/form-data; boundary=...` thay vì JSON mặc định.
  Future<ApiEnvelope> postMultipart(
    String path, {
    required FormData formData,
    bool skipAuth = false,
  }) {
    return _send(
      () => _dio.post(
        path,
        data: formData,
        options: Options(
          extra: {'skipAuth': skipAuth},
          contentType: 'multipart/form-data',
        ),
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
            : _fallbackMessage(status, rawBody: data),
        statusCode: status,
        errors: envelope.errors,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  static ApiErrorKind _kindFromStatus(int status) {
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
      default:
        return status >= 500 ? ApiErrorKind.server : ApiErrorKind.unknown;
    }
  }

  static String _fallbackMessage(int status, {Object? rawBody}) {
    debugPrint(
      '[DioClient] Không parse được message từ response (status $status) '
      'body=${_sanitize(rawBody)} → dùng fallback.',
    );
    switch (status) {
      case 400:
        return kDebugMode
            ? 'Server trả 400. Xem console dòng [API] để biết body lỗi.'
            : 'Yêu cầu không hợp lệ. Vui lòng kiểm tra cấu hình API.';
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
