import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../domain/shipper_scan_result.dart';

/// Lưu lịch sử quét nhận pickup của shipper trong phiên (local, MVP).
///
/// Giữ tối đa [maxItems] mục gần nhất, mới nhất ở đầu danh sách.
class RecentPickupScansStore {
  RecentPickupScansStore(this._prefs);

  final SharedPreferences _prefs;

  static const _key = 'shipper_recent_pickup_scans';
  static const maxItems = 50;

  /// Đọc toàn bộ lịch sử (mới nhất trước).
  List<RecentPickupScan> load() {
    final raw = _prefs.getString(_key);
    if (raw == null || raw.isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded
          .whereType<Map<String, dynamic>>()
          .map(RecentPickupScan.fromJson)
          .toList();
    } catch (_) {
      return const [];
    }
  }

  /// Thêm một mục lên đầu, cắt còn [maxItems], ghi lại. Trả danh sách mới.
  Future<List<RecentPickupScan>> add(RecentPickupScan scan) async {
    final current = load();
    final next = <RecentPickupScan>[scan, ...current];
    final trimmed = next.length > maxItems ? next.sublist(0, maxItems) : next;
    await _persist(trimmed);
    return trimmed;
  }

  /// Xóa toàn bộ lịch sử.
  Future<void> clear() async {
    await _prefs.remove(_key);
  }

  Future<void> _persist(List<RecentPickupScan> items) async {
    final encoded = jsonEncode(items.map((e) => e.toJson()).toList());
    await _prefs.setString(_key, encoded);
  }
}
