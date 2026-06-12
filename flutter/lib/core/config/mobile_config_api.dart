import '../api/dio_client.dart';

/// Cấu hình public lấy từ `GET /api/mobile/config`.
///
/// Chỉ chứa giá trị không nhạy cảm mà client cần để render (vd VietMap tile key
/// dùng tải tile/style trực tiếp từ maps.vietmap.vn).
class MobileConfig {
  const MobileConfig({this.vietmapTileApiKey = ''});

  final String vietmapTileApiKey;

  bool get hasVietmapTileKey => vietmapTileApiKey.isNotEmpty;

  factory MobileConfig.fromJson(Map<String, dynamic> json) {
    final vietmap = json['vietmap'];
    final tileKey = vietmap is Map<String, dynamic>
        ? (vietmap['tile_api_key']?.toString() ?? '')
        : '';
    return MobileConfig(vietmapTileApiKey: tileKey);
  }
}

/// Gọi endpoint cấu hình public của Mobile API.
class MobileConfigApi {
  MobileConfigApi(this._client);

  final DioClient _client;

  /// `GET /config` — trả [MobileConfig] (envelope chuẩn `data`).
  Future<MobileConfig> fetch() async {
    final envelope = await _client.get('/config');
    return MobileConfig.fromJson(envelope.dataMap);
  }
}
