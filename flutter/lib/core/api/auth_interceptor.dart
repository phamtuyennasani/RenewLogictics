import 'package:dio/dio.dart';

import '../storage/secure_token_storage.dart';

/// Gắn `Authorization: Bearer {token}` và `Accept: application/json` vào mọi request.
///
/// Khi backend trả 401 (token hỏng/hết hạn), gọi [onUnauthorized] để app
/// xóa token và điều hướng về Login. Interceptor KHÔNG tự retry login.
class AuthInterceptor extends Interceptor {
  AuthInterceptor({
    required SecureTokenStorage tokenStorage,
    required this.onUnauthorized,
  }) : _tokenStorage = tokenStorage;

  final SecureTokenStorage _tokenStorage;
  final Future<void> Function() onUnauthorized;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    options.headers['Accept'] = 'application/json';
    // Login không cần token; các route khác gắn Bearer nếu có.
    if (options.extra['skipAuth'] != true) {
      final token = await _tokenStorage.read();
      if (token != null && token.isNotEmpty) {
        options.headers['Authorization'] = 'Bearer $token';
      }
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    if (err.response?.statusCode == 401 && err.requestOptions.extra['skipAuth'] != true) {
      await _tokenStorage.clear();
      await onUnauthorized();
    }
    handler.next(err);
  }
}
