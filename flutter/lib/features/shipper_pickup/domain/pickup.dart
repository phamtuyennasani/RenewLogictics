import '../../../core/models/status_badge.dart';

/// Khách hàng/địa chỉ lấy hàng (từ JSON `info_khachhang`, contract §3.1).
class PickupCustomer {
  const PickupCustomer({
    this.company,
    this.fullname,
    this.phone,
    this.address,
    this.country,
  });

  final String? company;
  final String? fullname;
  final String? phone;
  final String? address;
  final String? country;

  factory PickupCustomer.fromJson(Map<String, dynamic> json) {
    return PickupCustomer(
      company: json['company']?.toString(),
      fullname: json['fullname']?.toString(),
      phone: json['phone']?.toString(),
      address: json['address']?.toString(),
      country: json['country']?.toString(),
    );
  }

  String get displayName {
    final c = company?.trim();
    final n = fullname?.trim();
    if (c != null && c.isNotEmpty) return c;
    if (n != null && n.isNotEmpty) return n;
    return 'Khách hàng';
  }
}

/// Tọa độ lấy hàng (contract §3.1).
class PickupLocation {
  const PickupLocation({this.lat, this.lng, this.hasLocation = false});

  final double? lat;
  final double? lng;
  final bool hasLocation;

  factory PickupLocation.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const PickupLocation();
    return PickupLocation(
      lat: (json['lat'] as num?)?.toDouble(),
      lng: (json['lng'] as num?)?.toDouble(),
      hasLocation: json['has_location'] == true,
    );
  }
}

/// Đơn hàng trong pickup (contract §3.2 `orders[]`).
class PickupOrderRef {
  const PickupOrderRef({
    required this.id,
    this.idBill,
    this.trackingCode,
    this.uuid,
  });

  final int id;
  final String? idBill;
  final String? trackingCode;
  final String? uuid;

  factory PickupOrderRef.fromJson(Map<String, dynamic> json) {
    return PickupOrderRef(
      id: (json['id'] as num?)?.toInt() ?? 0,
      idBill: json['id_bill']?.toString(),
      trackingCode: json['tracking_code']?.toString(),
      uuid: json['uuid']?.toString(),
    );
  }
}

/// Pickup item ở danh sách (contract §3.1).
class Pickup {
  const Pickup({
    required this.id,
    required this.maPickup,
    required this.status,
    required this.customer,
    required this.location,
    required this.allowedTransitions,
    this.scheduledAt,
    this.packageCount,
    this.weightKg,
    this.ordersCount,
    this.note,
    this.createdBy,
  });

  final int id;
  final String maPickup;
  final StatusBadge status;
  final PickupCustomer customer;
  final PickupLocation location;
  final List<StatusBadge> allowedTransitions;
  final DateTime? scheduledAt;
  final int? packageCount;
  final double? weightKg;
  final int? ordersCount;
  final String? note;
  final String? createdBy;

  factory Pickup.fromJson(Map<String, dynamic> json) {
    return Pickup(
      id: (json['id'] as num?)?.toInt() ?? 0,
      maPickup: (json['ma_pickup'] ?? '').toString(),
      status: StatusBadge.fromJson(
        json['status'] is Map<String, dynamic>
            ? json['status'] as Map<String, dynamic>
            : const {},
      ),
      customer: PickupCustomer.fromJson(
        json['customer'] is Map<String, dynamic>
            ? json['customer'] as Map<String, dynamic>
            : const {},
      ),
      location: PickupLocation.fromJson(
        json['location'] as Map<String, dynamic>?,
      ),
      allowedTransitions:
          StatusBadge.listFrom(json['allowed_transitions']),
      scheduledAt: _parseDate(json['scheduled_at']),
      packageCount: (json['package_count'] as num?)?.toInt(),
      weightKg: (json['weight_kg'] as num?)?.toDouble(),
      ordersCount: (json['orders_count'] as num?)?.toInt(),
      note: json['note']?.toString(),
      createdBy: json['created_by']?.toString(),
    );
  }

  static DateTime? _parseDate(Object? raw) {
    if (raw == null) return null;
    return DateTime.tryParse(raw.toString());
  }
}

/// Chi tiết pickup (contract §3.2) — mở rộng từ list item.
class PickupDetail {
  const PickupDetail({
    required this.pickup,
    required this.orders,
    this.weightGrossKg,
    this.createdAt,
  });

  final Pickup pickup;
  final List<PickupOrderRef> orders;
  final double? weightGrossKg;
  final DateTime? createdAt;

  factory PickupDetail.fromJson(Map<String, dynamic> json) {
    final orders = (json['orders'] is List)
        ? (json['orders'] as List)
            .whereType<Map<String, dynamic>>()
            .map(PickupOrderRef.fromJson)
            .toList()
        : <PickupOrderRef>[];

    return PickupDetail(
      pickup: Pickup.fromJson(json),
      orders: orders,
      weightGrossKg: (json['weight_gross_kg'] as num?)?.toDouble(),
      createdAt: Pickup._parseDate(json['created_at']),
    );
  }
}
