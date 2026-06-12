import '../domain/shipper_scan_repository.dart';
import '../domain/shipper_scan_result.dart';
import 'shipper_scan_api.dart';

/// Triển khai [ShipperScanRepository]: gọi [ShipperScanApi], map envelope → domain.
class ShipperScanRepositoryImpl implements ShipperScanRepository {
  ShipperScanRepositoryImpl(this._api);

  final ShipperScanApi _api;

  @override
  Future<ShipperScanResult> scan(String code) async {
    final env = await _api.scan(code);
    return ShipperScanResult.fromJson(env.dataMap);
  }

  @override
  Future<ShipperReceiveResult> receiveByScan({
    required int pickupId,
    required int orderId,
  }) async {
    final env = await _api.receiveByScan(pickupId: pickupId, orderId: orderId);
    return ShipperReceiveResult.fromJson(env.dataMap, message: env.message);
  }
}
