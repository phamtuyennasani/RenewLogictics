import '../../../core/models/status_badge.dart';
import '../../shipper_pickup/domain/pickup.dart';

/// Kết quả quét mã kiện của shipper (POST /shipper/scan).
///
/// Tái dùng [Pickup] từ feature shipper_pickup làm payload pickup — backend trả
/// `pickupPayload(detailed:true)` đúng cấu trúc [Pickup.fromJson] đọc được.
class ShipperScanResult {
  const ShipperScanResult({
    required this.found,
    required this.canReceive,
    required this.packageCode,
    this.orderId,
    this.orderCode,
    this.pickup,
    this.reason,
  });

  /// Có tìm thấy pickup được gán cho shipper từ mã kiện này không.
  final bool found;

  /// Có thể nhận hàng (pickup chưa ở trạng thái đã lấy).
  final bool canReceive;

  /// Mã kiện đã quét.
  final String packageCode;

  /// Id đơn hàng khớp mã kiện (cần khi gọi receive-by-scan).
  final int? orderId;

  /// Mã đơn hiển thị (id_bill / tracking_code).
  final String? orderCode;

  /// Thông tin pickup tìm được (null khi found=false).
  final Pickup? pickup;

  /// Lý do không nhận được / không tìm thấy.
  final String? reason;

  factory ShipperScanResult.fromJson(Map<String, dynamic> json) {
    final pickupJson = json['pickup'];
    return ShipperScanResult(
      found: json['found'] == true,
      canReceive: json['can_receive'] == true,
      packageCode: (json['package_code'] ?? '').toString(),
      orderId: (json['order_id'] as num?)?.toInt(),
      orderCode: json['order_code']?.toString(),
      pickup: pickupJson is Map<String, dynamic>
          ? Pickup.fromJson(pickupJson)
          : null,
      reason: json['reason']?.toString(),
    );
  }
}

/// Kết quả nhận hàng sau khi quét (POST /shipper/pickups/receive-by-scan).
class ShipperReceiveResult {
  const ShipperReceiveResult({
    required this.pickupId,
    required this.status,
    this.message,
  });

  final int pickupId;
  final StatusBadge status;
  final String? message;

  factory ShipperReceiveResult.fromJson(
    Map<String, dynamic> data, {
    String? message,
  }) {
    return ShipperReceiveResult(
      pickupId: (data['id'] as num?)?.toInt() ?? 0,
      status: StatusBadge.fromJson(
        data['status'] is Map<String, dynamic>
            ? data['status'] as Map<String, dynamic>
            : const {},
      ),
      message: message,
    );
  }
}

/// Một mục lịch sử quét của shipper (lưu local trong phiên).
class RecentPickupScan {
  const RecentPickupScan({
    required this.code,
    required this.scannedAt,
    required this.received,
    this.pickupCode,
    this.statusLabel,
    this.note,
  });

  final String code;
  final DateTime scannedAt;

  /// Đã nhận hàng thành công.
  final bool received;

  final String? pickupCode;
  final String? statusLabel;

  /// Ghi chú (lý do không tìm thấy / không nhận được).
  final String? note;

  Map<String, dynamic> toJson() => {
        'code': code,
        'scanned_at': scannedAt.toIso8601String(),
        'received': received,
        'pickup_code': pickupCode,
        'status_label': statusLabel,
        'note': note,
      };

  factory RecentPickupScan.fromJson(Map<String, dynamic> json) {
    return RecentPickupScan(
      code: (json['code'] ?? '').toString(),
      scannedAt:
          DateTime.tryParse(json['scanned_at']?.toString() ?? '') ??
              DateTime.now(),
      received: json['received'] == true,
      pickupCode: json['pickup_code']?.toString(),
      statusLabel: json['status_label']?.toString(),
      note: json['note']?.toString(),
    );
  }
}
