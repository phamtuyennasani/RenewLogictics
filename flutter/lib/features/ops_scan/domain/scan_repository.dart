import 'scan_result.dart';

/// Một dòng kết quả nhập kho hàng loạt (contract §4.3).
class BulkReceiveItem {
  const BulkReceiveItem({
    required this.code,
    required this.success,
    this.orderId,
    this.status,
    this.reason,
  });

  final String code;
  final bool success;
  final int? orderId;

  /// status `value` khi thành công (vd `da_nhan_hang`).
  final String? status;

  /// Lý do khi thất bại.
  final String? reason;

  factory BulkReceiveItem.success(Map<String, dynamic> json) {
    return BulkReceiveItem(
      code: (json['code'] ?? '').toString(),
      success: true,
      orderId: (json['order_id'] as num?)?.toInt(),
      status: json['status']?.toString(),
    );
  }

  factory BulkReceiveItem.failure(Map<String, dynamic> json) {
    return BulkReceiveItem(
      code: (json['code'] ?? '').toString(),
      success: false,
      orderId: (json['order_id'] as num?)?.toInt(),
      reason: json['reason']?.toString(),
    );
  }
}

/// Kết quả nhập kho hàng loạt (contract §4.3).
class BulkReceiveResult {
  const BulkReceiveResult({
    required this.succeeded,
    required this.failed,
    this.message,
  });

  final List<BulkReceiveItem> succeeded;
  final List<BulkReceiveItem> failed;
  final String? message;

  int get total => succeeded.length + failed.length;

  factory BulkReceiveResult.fromJson(
    Map<String, dynamic> data, {
    String? message,
  }) {
    List<BulkReceiveItem> parse(
      Object? raw,
      BulkReceiveItem Function(Map<String, dynamic>) mapper,
    ) {
      if (raw is! List) return const [];
      return raw.whereType<Map<String, dynamic>>().map(mapper).toList();
    }

    return BulkReceiveResult(
      succeeded: parse(data['succeeded'], BulkReceiveItem.success),
      failed: parse(data['failed'], BulkReceiveItem.failure),
      message: message,
    );
  }
}

/// Contract repository OPS scan — presentation chỉ phụ thuộc abstraction này.
abstract class ScanRepository {
  /// Tra cứu đơn theo mã quét (chỉ đọc, contract §4.1).
  Future<ScanResult> scan(String code);

  /// Xác nhận nhập kho một đơn (contract §4.2).
  ///
  /// [code] optional để backend đối chiếu lại mã đã quét.
  Future<ReceiveResult> receive({required int orderId, String? code});

  /// Nhập kho hàng loạt theo danh sách mã (contract §4.3).
  Future<BulkReceiveResult> bulkReceiveByCodes(List<String> codes);
}
