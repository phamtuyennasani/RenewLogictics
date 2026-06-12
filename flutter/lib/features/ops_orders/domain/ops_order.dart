import '../../../core/models/status_badge.dart';

/// Sender thông tin người gửi (từ JSON `sender`, backend payload).
class OrderSender {
  const OrderSender({
    this.company,
    this.fullname,
    this.phone,
    this.email,
    this.address,
    this.country,
  });

  final String? company;
  final String? fullname;
  final String? phone;
  final String? email;
  final String? address;
  final String? country;

  factory OrderSender.fromJson(Map<String, dynamic> json) {
    return OrderSender(
      company: json['company']?.toString(),
      fullname: json['fullname']?.toString(),
      phone: json['phone']?.toString(),
      email: json['email']?.toString(),
      address: json['address']?.toString(),
      country: json['country']?.toString(),
    );
  }

  String get displayName {
    final c = company?.trim();
    final n = fullname?.trim();
    if (c != null && c.isNotEmpty) return c;
    if (n != null && n.isNotEmpty) return n;
    return 'Người gửi';
  }
}

/// Receiver thông tin người nhận (từ JSON `receiver`).
class OrderReceiver {
  const OrderReceiver({this.fullname, this.country});

  final String? fullname;
  final String? country;

  factory OrderReceiver.fromJson(Map<String, dynamic> json) {
    return OrderReceiver(
      fullname: json['fullname']?.toString(),
      country: json['country']?.toString(),
    );
  }
}

/// Package kiện hàng trong order detail.
class OrderPackage {
  const OrderPackage({
    required this.id,
    required this.numberOfPackage,
    required this.cWeight,
  });

  final int id;
  final int numberOfPackage;
  final double cWeight;

  factory OrderPackage.fromJson(Map<String, dynamic> json) {
    return OrderPackage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      numberOfPackage: (json['number_of_package'] as num?)?.toInt() ?? 0,
      cWeight: (json['c_weight'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

/// Order item ở danh sách (backend list payload).
class OpsOrder {
  const OpsOrder({
    required this.id,
    required this.idBill,
    required this.status,
    required this.sender,
    required this.receiver,
    this.trackingCode,
    this.mathamchieu,
    this.packageCount,
    this.weightKg,
    this.saleName,
    this.hasPickup = false,
    this.createdAt,
  });

  final int id;
  final String idBill;
  final StatusBadge status;
  final OrderSender sender;
  final OrderReceiver receiver;
  final String? trackingCode;
  final String? mathamchieu;
  final int? packageCount;
  final double? weightKg;
  final String? saleName;
  final bool hasPickup;
  final DateTime? createdAt;

  factory OpsOrder.fromJson(Map<String, dynamic> json) {
    return OpsOrder(
      id: (json['id'] as num?)?.toInt() ?? 0,
      idBill: (json['id_bill'] ?? '').toString(),
      status: StatusBadge.fromJson(
        json['status'] is Map<String, dynamic>
            ? json['status'] as Map<String, dynamic>
            : const {},
      ),
      sender: OrderSender.fromJson(
        json['sender'] is Map<String, dynamic>
            ? json['sender'] as Map<String, dynamic>
            : const {},
      ),
      receiver: OrderReceiver.fromJson(
        json['receiver'] is Map<String, dynamic>
            ? json['receiver'] as Map<String, dynamic>
            : const {},
      ),
      trackingCode: json['tracking_code']?.toString(),
      mathamchieu: json['mathamchieu']?.toString(),
      packageCount: (json['package_count'] as num?)?.toInt(),
      weightKg: (json['weight_kg'] as num?)?.toDouble(),
      saleName: json['sale_name']?.toString(),
      hasPickup: json['has_pickup'] == true,
      createdAt: _parseDate(json['created_at']),
    );
  }

  static DateTime? _parseDate(Object? raw) {
    if (raw == null) return null;
    return DateTime.tryParse(raw.toString());
  }
}

/// Chi tiết order (backend detail payload) — mở rộng từ list item.
class OpsOrderDetail {
  const OpsOrderDetail({
    required this.order,
    required this.packages,
    this.note,
    this.canCreatePickup = false,
  });

  final OpsOrder order;
  final List<OrderPackage> packages;
  final String? note;
  final bool canCreatePickup;

  factory OpsOrderDetail.fromJson(Map<String, dynamic> json) {
    final packages = (json['packages'] is List)
        ? (json['packages'] as List)
            .whereType<Map<String, dynamic>>()
            .map(OrderPackage.fromJson)
            .toList()
        : <OrderPackage>[];

    return OpsOrderDetail(
      order: OpsOrder.fromJson(json),
      packages: packages,
      note: json['note']?.toString(),
      canCreatePickup: json['can_create_pickup'] == true,
    );
  }
}
