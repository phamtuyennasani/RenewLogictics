import '../../../core/api/api_envelope.dart';
import '../../../core/api/dio_client.dart';

/// Gọi endpoint OPS scan/nhập kho thô (contract §4). Trả [ApiEnvelope].
class ScanApi {
  ScanApi(this._client);

  final DioClient _client;

  /// `POST /ops/scan` — tra cứu đơn theo mã quét (chỉ đọc).
  Future<ApiEnvelope> scan(String code) {
    return _client.post('/ops/scan', body: {'code': code});
  }

  /// `POST /ops/orders/{order}/receive` — xác nhận nhập kho một đơn.
  Future<ApiEnvelope> receive({required int orderId, String? code}) {
    return _client.post(
      '/ops/orders/$orderId/receive',
      body: {if (code != null && code.isNotEmpty) 'code': code},
    );
  }

  /// `POST /ops/orders/bulk-receive` — nhập kho hàng loạt theo mã.
  Future<ApiEnvelope> bulkReceiveByCodes(List<String> codes) {
    return _client.post('/ops/orders/bulk-receive', body: {'codes': codes});
  }
}
