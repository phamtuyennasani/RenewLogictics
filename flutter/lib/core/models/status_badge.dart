/// Badge trạng thái chuẩn contract §1.7: `{ "value": "...", "label": "...", "color": "..." }`.
///
/// `color` từ backend là Tailwind class (vd `bg-amber-100 text-amber-700`) —
/// KHÔNG dùng trực tiếp ở Flutter. UI map `value` → màu Material qua StatusPalette.
class StatusBadge {
  const StatusBadge({
    required this.value,
    required this.label,
    this.rawColor,
  });

  final String value;
  final String label;

  /// Tailwind class gốc (giữ lại để debug, không render trực tiếp).
  final String? rawColor;

  factory StatusBadge.fromJson(Map<String, dynamic> json) {
    return StatusBadge(
      value: (json['value'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      rawColor: json['color']?.toString(),
    );
  }

  /// Cho phép parse một transition dạng `{ value, label }` (không có color).
  static List<StatusBadge> listFrom(Object? raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map<String, dynamic>>()
        .map(StatusBadge.fromJson)
        .toList();
  }
}
