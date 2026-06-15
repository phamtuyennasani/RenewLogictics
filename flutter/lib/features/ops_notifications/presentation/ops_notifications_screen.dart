import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/widgets/empty_state.dart';

/// Màn thông báo OPS.
///
/// Hiện chưa có endpoint lịch sử thông báo (push chỉ realtime qua FCM), nên màn
/// này là placeholder. Khi backend mở `/api/mobile/notifications` thì nối list
/// vào đây — giữ nguyên vị trí tab để không phải đổi điều hướng.
class OpsNotificationsScreen extends ConsumerWidget {
  const OpsNotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: const Text('Thông báo')),
      body: const EmptyState(
        icon: Icons.notifications_none_outlined,
        title: 'Chưa có thông báo',
        message:
            'Thông báo về pickup và đơn hàng sẽ hiển thị ở đây khi có cập nhật mới.',
      ),
    );
  }
}
