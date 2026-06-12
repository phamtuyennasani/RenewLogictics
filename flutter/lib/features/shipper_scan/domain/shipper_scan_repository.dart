import 'shipper_scan_result.dart';

/// Contract repository shipper scan — presentation chỉ phụ thuộc abstraction này.
abstract class ShipperScanRepository {
  /// Quét mã kiện → tìm pickup được gán cho shipper (chỉ đọc).
  Future<ShipperScanResult> scan(String code);

  /// Nhận hàng pickup sau khi quét (chuyển sang đã lấy hàng).
  Future<ShipperReceiveResult> receiveByScan({
    required int pickupId,
    required int orderId,
  });
}
