import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Một thao tác đổi trạng thái pickup đang chờ đồng bộ (lưu khi offline).
class PendingStatusAction {
  const PendingStatusAction({
    required this.pickupId,
    required this.status,
    required this.queuedAtMs,
    this.reason,
    this.lat,
    this.lng,
  });

  final int pickupId;
  final String status;
  final String? reason;
  final double? lat;
  final double? lng;

  /// Thời điểm xếp hàng (millisecondsSinceEpoch) — để hiển thị / sắp xếp.
  final int queuedAtMs;

  Map<String, dynamic> toJson() => {
        'pickup_id': pickupId,
        'status': status,
        if (reason != null) 'reason': reason,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        'queued_at_ms': queuedAtMs,
      };

  factory PendingStatusAction.fromJson(Map<String, dynamic> json) {
    return PendingStatusAction(
      pickupId: (json['pickup_id'] as num?)?.toInt() ?? 0,
      status: (json['status'] ?? '').toString(),
      reason: json['reason']?.toString(),
      lat: (json['lat'] as num?)?.toDouble(),
      lng: (json['lng'] as num?)?.toDouble(),
      queuedAtMs: (json['queued_at_ms'] as num?)?.toInt() ?? 0,
    );
  }
}

/// Hàng đợi thao tác đổi trạng thái pickup chờ đồng bộ, lưu ở SharedPreferences.
///
/// KHÔNG nhạy cảm (không token/tài chính) nên dùng shared_preferences hợp lệ.
/// Mỗi pickup chỉ giữ MỘT action mới nhất — thao tác sau ghi đè cùng pickupId,
/// tránh queue phình khi shipper bấm nhiều lần / đổi qua lại.
class PendingStatusStore {
  PendingStatusStore(this._prefs);

  final SharedPreferences _prefs;

  static const _key = 'pending_status_actions';

  /// Đọc toàn bộ hàng đợi (cũ → mới theo thứ tự lưu).
  List<PendingStatusAction> all() {
    final raw = _prefs.getString(_key);
    if (raw == null || raw.isEmpty) return [];
    try {
      final list = jsonDecode(raw);
      if (list is! List) return [];
      return list
          .whereType<Map<String, dynamic>>()
          .map(PendingStatusAction.fromJson)
          .toList();
    } catch (_) {
      return [];
    }
  }

  /// Thêm/cập nhật action cho pickup (ghi đè action cũ cùng pickupId).
  Future<void> upsert(PendingStatusAction action) async {
    final items = all()
        .where((a) => a.pickupId != action.pickupId)
        .toList()
      ..add(action);
    await _save(items);
  }

  /// Xóa action của một pickup (sau khi đồng bộ xong hoặc bỏ).
  Future<void> removeByPickup(int pickupId) async {
    final items = all().where((a) => a.pickupId != pickupId).toList();
    await _save(items);
  }

  /// Có action chờ đồng bộ cho pickup này không.
  bool hasPending(int pickupId) =>
      all().any((a) => a.pickupId == pickupId);

  Future<void> clear() => _prefs.remove(_key);

  Future<void> _save(List<PendingStatusAction> items) async {
    final encoded = jsonEncode(items.map((a) => a.toJson()).toList());
    await _prefs.setString(_key, encoded);
  }
}
