import '../../../core/models/status_badge.dart';

/// Cách backend đối khớp mã quét (contract §4.1 `matched_by`).
enum ScanMatchedBy {
  idBill('id_bill'),
  trackingCode('tracking_code'),
  mathamchieu('mathamchieu'),
  packageCode('package_code'),
  unknown('');

  const ScanMatchedBy(this.value);

  final String value;

  static ScanMatchedBy fromValue(String? raw) {
    if (raw == null) return ScanMatchedBy.unknown;
    for (final v in ScanMatchedBy.values) {
      if (v.value == raw) return v;
    }
    return ScanMatchedBy.unknown;
  }

  String get label {
    switch (this) {
      case ScanMatchedBy.idBill:
        return 'Mã vận đơn';
      case ScanMatchedBy.trackingCode:
        return 'Tracking';
      case ScanMatchedBy.mathamchieu:
        return 'Mã tham chiếu';
      case ScanMatchedBy.packageCode:
        return 'Mã kiện';
      case ScanMatchedBy.unknown:
        return '';
    }
  }
}

/// Bên gửi/nhận rút gọn (contract §4.1). KHÔNG có thông tin tài chính.
class ScanParty {
  const ScanParty({this.company, this.fullname, this.phone, this.country});

  final String? company;
  final String? fullname;
  final String? phone;
  final String? country;

  factory ScanParty.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const ScanParty();
    return ScanParty(
      company: json['company']?.toString(),
      fullname: json['fullname']?.toString(),
      phone: json['phone']?.toString(),
      country: json['country']?.toString(),
    );
  }

  String get displayName {
    final c = company?.trim();
    final n = fullname?.trim();
    if (c != null && c.isNotEmpty) return c;
    if (n != null && n.isNotEmpty) return n;
    return '—';
  }
}

/// Đơn hàng trả về khi scan (contract §4.1 `order`).
class ScanOrder {
  const ScanOrder({
    required this.id,
    required this.status,
    this.idBill,
    this.trackingCode,
    this.mathamchieu,
    this.sender,
    this.receiver,
    this.packageCount,
    this.weightKg,
    this.saleName,
    this.locked = false,
    this.receivedAt,
  });

  final int id;
  final StatusBadge status;
  final String? idBill;
  final String? trackingCode;
  final String? mathamchieu;
  final ScanParty? sender;
  final ScanParty? receiver;
  final int? packageCount;
  final double? weightKg;
  final String? saleName;
  final bool locked;
  final DateTime? receivedAt;

  factory ScanOrder.fromJson(Map<String, dynamic> json) {
    return ScanOrder(
      id: (json['id'] as num?)?.toInt() ?? 0,
      status: StatusBadge.fromJson(
        json['status'] is Map<String, dynamic>
            ? json['status'] as Map<String, dynamic>
            : const {},
      ),
      idBill: json['id_bill']?.toString(),
      trackingCode: json['tracking_code']?.toString(),
      mathamchieu: json['mathamchieu']?.toString(),
      sender: ScanParty.fromJson(json['sender'] as Map<String, dynamic>?),
      receiver: ScanParty.fromJson(json['receiver'] as Map<String, dynamic>?),
      packageCount: (json['package_count'] as num?)?.toInt(),
      weightKg: (json['weight_kg'] as num?)?.toDouble(),
      saleName: json['sale_name']?.toString(),
      locked: json['locked'] == true,
      receivedAt: _parseDate(json['received_at']),
    );
  }

  static DateTime? _parseDate(Object? raw) {
    if (raw == null) return null;
    return DateTime.tryParse(raw.toString());
  }

  /// Mã hiển thị chính của đơn (ưu tiên id_bill).
  String get primaryCode =>
      idBill ?? trackingCode ?? mathamchieu ?? '#$id';
}

/// Kết quả tra cứu scan (contract §4.1).
class ScanResult {
  const ScanResult({
    required this.found,
    required this.canReceive,
    this.matchedBy = ScanMatchedBy.unknown,
    this.matchedPackageCode,
    this.order,
    this.reason,
  });

  final bool found;
  final bool canReceive;
  final ScanMatchedBy matchedBy;
  final String? matchedPackageCode;
  final ScanOrder? order;

  /// Lý do không nhập kho được (khi [canReceive] = false).
  final String? reason;

  factory ScanResult.fromJson(Map<String, dynamic> json) {
    return ScanResult(
      found: json['found'] == true,
      canReceive: json['can_receive'] == true,
      matchedBy: ScanMatchedBy.fromValue(json['matched_by']?.toString()),
      matchedPackageCode: json['matched_package_code']?.toString(),
      order: json['order'] is Map<String, dynamic>
          ? ScanOrder.fromJson(json['order'] as Map<String, dynamic>)
          : null,
      reason: json['reason']?.toString(),
    );
  }
}

/// Kết quả nhập kho một đơn (contract §4.2).
class ReceiveResult {
  const ReceiveResult({
    required this.orderId,
    required this.status,
    this.idBill,
    this.receivedAt,
    this.message,
  });

  final int orderId;
  final StatusBadge status;
  final String? idBill;
  final DateTime? receivedAt;
  final String? message;

  factory ReceiveResult.fromJson(
    Map<String, dynamic> data, {
    String? message,
  }) {
    final order = data['order'] is Map<String, dynamic>
        ? data['order'] as Map<String, dynamic>
        : const <String, dynamic>{};
    return ReceiveResult(
      orderId: (order['id'] as num?)?.toInt() ?? 0,
      status: StatusBadge.fromJson(
        order['status'] is Map<String, dynamic>
            ? order['status'] as Map<String, dynamic>
            : const {},
      ),
      idBill: order['id_bill']?.toString(),
      receivedAt: ScanOrder._parseDate(order['received_at']),
      message: message,
    );
  }
}

/// Mục lịch sử scan lưu local trong phiên (contract §4.4 — MVP client-side).
class RecentScan {
  const RecentScan({
    required this.code,
    required this.scannedAt,
    required this.received,
    this.orderId,
    this.idBill,
    this.statusLabel,
    this.note,
  });

  /// Mã đã quét.
  final String code;
  final DateTime scannedAt;

  /// Đã nhập kho thành công hay chưa.
  final bool received;
  final int? orderId;
  final String? idBill;
  final String? statusLabel;

  /// Ghi chú (vd lý do không nhập được).
  final String? note;

  Map<String, dynamic> toJson() => {
        'code': code,
        'scanned_at': scannedAt.toIso8601String(),
        'received': received,
        'order_id': orderId,
        'id_bill': idBill,
        'status_label': statusLabel,
        'note': note,
      };

  factory RecentScan.fromJson(Map<String, dynamic> json) {
    return RecentScan(
      code: (json['code'] ?? '').toString(),
      scannedAt: DateTime.tryParse(json['scanned_at']?.toString() ?? '') ??
          DateTime.now(),
      received: json['received'] == true,
      orderId: (json['order_id'] as num?)?.toInt(),
      idBill: json['id_bill']?.toString(),
      statusLabel: json['status_label']?.toString(),
      note: json['note']?.toString(),
    );
  }
}
