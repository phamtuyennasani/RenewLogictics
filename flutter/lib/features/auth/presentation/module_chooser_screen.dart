import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'auth_controller.dart';

/// Màn chọn module khi user có cả role shipper lẫn OPS (`default_module = chooser`).
class ModuleChooserScreen extends ConsumerWidget {
  const ModuleChooserScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authControllerProvider).session;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chọn chế độ làm việc'),
        actions: [
          IconButton(
            tooltip: 'Đăng xuất',
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (session != null)
                Text(
                  'Xin chào, ${session.user.fullname.isNotEmpty ? session.user.fullname : session.user.username}',
                  style: theme.textTheme.titleMedium,
                  textAlign: TextAlign.center,
                ),
              const SizedBox(height: 24),
              if (session?.isShipper ?? false)
                _ModuleCard(
                  icon: Icons.local_shipping_rounded,
                  title: 'Shipper',
                  subtitle: 'Nhận và cập nhật pickup',
                  onTap: () => context.go('/shipper'),
                ),
              if (session?.isOpsCapable ?? false) ...[
                const SizedBox(height: 16),
                _ModuleCard(
                  icon: Icons.qr_code_scanner_rounded,
                  title: 'OPS',
                  subtitle: 'Quét mã & nhập kho',
                  onTap: () => context.go('/ops'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: theme.colorScheme.primaryContainer,
                child: Icon(icon, size: 28, color: theme.colorScheme.primary),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: theme.textTheme.titleLarge),
                    const SizedBox(height: 4),
                    Text(subtitle,
                        style: theme.textTheme.bodyMedium
                            ?.copyWith(color: theme.hintColor)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}
