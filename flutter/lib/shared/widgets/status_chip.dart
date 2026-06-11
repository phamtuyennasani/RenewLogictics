import 'package:flutter/material.dart';

import '../../core/models/status_badge.dart';
import '../../core/utils/status_palette.dart';

/// Chip hiển thị trạng thái (pickup/order). Màu lấy từ [StatusPalette] theo
/// `value`, KHÔNG dùng Tailwind class `color` mà backend trả.
class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.badge, this.dense = false});

  final StatusBadge badge;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final palette = StatusPalette.of(badge.value);
    final label = badge.label.trim().isEmpty ? badge.value : badge.label;

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: dense ? 8 : 11,
        vertical: dense ? 4 : 6,
      ),
      decoration: BoxDecoration(
        color: palette.bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: palette.fg.withValues(alpha: 0.14)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: palette.fg,
          fontSize: dense ? 11 : 12,
          fontWeight: FontWeight.w800,
          height: 1.1,
        ),
      ),
    );
  }
}
