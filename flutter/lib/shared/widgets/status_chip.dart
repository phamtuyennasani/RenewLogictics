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
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: dense ? 8 : 10,
        vertical: dense ? 3 : 5,
      ),
      decoration: BoxDecoration(
        color: palette.bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        badge.label,
        style: TextStyle(
          color: palette.fg,
          fontSize: dense ? 11 : 12.5,
          fontWeight: FontWeight.w600,
          height: 1.1,
        ),
      ),
    );
  }
}
