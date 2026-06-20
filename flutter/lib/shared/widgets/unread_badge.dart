import 'package:flutter/material.dart';

/// Badge số chưa đọc dùng chung (chuông thông báo shipper, tab OPS...).
///
/// Bọc [child] bằng Material [Badge]; tự ẩn khi [count] <= 0 và hiển thị
/// `99+` khi vượt 99. Gom style về một chỗ để badge đồng bộ ở mọi nơi.
class UnreadBadge extends StatelessWidget {
  const UnreadBadge({super.key, required this.count, required this.child});

  final int count;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Badge(
      isLabelVisible: count > 0,
      label: Text(count > 99 ? '99+' : '$count'),
      child: child,
    );
  }
}
