import '../../../core/api/api_envelope.dart';
import '../../../core/api/dio_client.dart';

/// Gọi endpoint shipper scan/nhận hàng thô. Trả [ApiEnvelope].
class ShipperScanApi {
  ShipperScanApi(this._client);

  final DioClient _client;

  /// `POST /shipper/scan` — quét mã kiện, tìm pickup (chỉ đọc).
  Future<ApiEnvelope> scan(String code) {
    return _client.post('/shipper/scan', body: {'code': code});
  }

  /// `POST /shipper/pickups/receive-by-scan` — nhận hàng pickup sau khi quét.
  Future<ApiEnvelope> receiveByScan({
    required int pickupId,
    required int orderId,
  }) {
    return _client.post(
      '/shipper/pickups/receive-by-scan',
      body: {'pickup_id': pickupId, 'order_id': orderId},
    );
  }
}
