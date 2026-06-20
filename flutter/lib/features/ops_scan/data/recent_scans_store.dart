import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../domain/scan_result.dart';

/// Lưu lịch sử scan trong phiên hiện tại (contract §4.4 — MVP client-side).
///
/// Theo quyết định MVP: lưu local qua [SharedPreferences] thay vì gọi server.
/// Giữ tối đa [maxItems] mục gần nhất, mới nhất ở đầu danh sách.
class RecentScansStore {
  RecentScansStore(this._prefs);

  final SharedPreferences _prefs;

  static const _key = 'ops_recent_scans';
  static const maxItems = 50;

  /// Đọc toàn bộ lịch sử (mới nhất trước).
  List<RecentScan> load() {
    final raw = _prefs.getString(_key);
    if (raw == null || raw.isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded
          .whereType<Map<String, dynamic>>()
          .map(RecentScan.fromJson)
          .toList();
    } catch (_) {
      // Dữ liệu hỏng → bỏ qua, coi như rỗng.
      return const [];
    }
  }

  /// Thêm một mục lên đầu, cắt còn [maxItems], ghi lại. Trả danh sách mới.
  Future<List<RecentScan>> add(RecentScan scan) async {
    final current = load();
    final next = <RecentScan>[scan, ...current];
    final trimmed = next.length > maxItems ? next.sublist(0, maxItems) : next;
    await _persist(trimmed);
    return trimmed;
  }

  /// Xóa toàn bộ lịch sử.
  Future<void> clear() async {
    await _prefs.remove(_key);
  }

  Future<void> _persist(List<RecentScan> items) async {
    final encoded = jsonEncode(items.map((e) => e.toJson()).toList());
    await _prefs.setString(_key, encoded);
  }
}
