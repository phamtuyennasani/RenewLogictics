import 'package:dio/dio.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/api/dio_client.dart';
import '../../../core/location/location_service.dart';
import '../domain/route_path.dart';

/// Gọi proxy VietMap của Mobile API (`/api/mobile/vietmap/*`).
///
/// LƯU Ý: proxy trả NGUYÊN JSON của VietMap (`{code, paths, ...}`), KHÔNG bọc
/// envelope `{success, message, data}`. Vì vậy ta dùng [DioClient.raw] trực tiếp
/// thay vì [DioClient.get] (vốn kỳ vọng envelope).
class VietmapApi {
  VietmapApi(this._client);

  final DioClient _client;

  /// `GET /vietmap/route` — tìm đường từ [origin] tới [destination].
  ///
  /// [vehicle] mặc định `motorcycle` (đồng nhất với bản web cho shipper).
  Future<RoutePath> route({
    required LatLngPoint origin,
    required LatLngPoint destination,
    String vehicle = 'motorcycle',
  }) async {
    final Response<dynamic> response;
    try {
      response = await _client.raw.get(
        '/vietmap/route',
        queryParameters: {
          'points_encoded': 'false',
          'vehicle': vehicle,
          'point[]': [
            '${origin.lat},${origin.lng}',
            '${destination.lat},${destination.lng}',
          ],
        },
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }

    final status = response.statusCode ?? 0;
    final data = response.data;

    if (status == 503) {
      throw ApiException(
        kind: ApiErrorKind.server,
        message: 'Chưa cấu hình VietMap API Key trên hệ thống.',
        statusCode: 503,
      );
    }

    if (status < 200 || status >= 300 || data is! Map<String, dynamic>) {
      throw ApiException(
        kind: ApiErrorKind.server,
        message: 'Không tải được tuyến đường từ VietMap.',
      );
    }

    final paths = data['paths'];
    if (data['code'] != 'OK' || paths is! List || paths.isEmpty) {
      final message = data['messages']?.toString();
      throw ApiException(
        kind: ApiErrorKind.notFound,
        message: (message != null && message.isNotEmpty)
            ? message
            : 'Không tìm thấy tuyến đường phù hợp.',
        statusCode: status,
      );
    }

    final first = paths.first;
    if (first is! Map<String, dynamic>) {
      throw ApiException(
        kind: ApiErrorKind.server,
        message: 'Dữ liệu tuyến đường không hợp lệ.',
      );
    }

    return RoutePath.fromVietmapPath(first);
  }
}
