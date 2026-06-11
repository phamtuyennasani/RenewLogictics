import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../auth/presentation/auth_controller.dart';

/// Màn profile nhanh: thông tin user, role, và đăng xuất.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authControllerProvider).session;
    final theme = Theme.of(context);
    final user = session?.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Tài khoản')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              color: theme.colorScheme.primary,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 34,
                    backgroundColor: Colors.white.withValues(alpha: 0.16),
                    backgroundImage:
                        (user?.avatar != null && user!.avatar!.isNotEmpty)
                        ? NetworkImage(user.avatar!)
                        : null,
                    child: (user?.avatar == null || user!.avatar!.isEmpty)
                        ? const Icon(
                            Icons.person,
                            size: 34,
                            color: Colors.white,
                          )
                        : null,
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user?.fullname.isNotEmpty == true
                              ? user!.fullname
                              : (user?.username ?? '—'),
                          style: theme.textTheme.titleLarge?.copyWith(
                            color: Colors.white,
                          ),
                        ),
                        if (user?.username != null)
                          Text(
                            '@${user!.username}',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: Colors.white.withValues(alpha: 0.74),
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Column(
                children: [
                  if (user?.phone != null && user!.phone!.isNotEmpty)
                    _InfoTile(
                      icon: Icons.phone_outlined,
                      title: 'Điện thoại',
                      value: user.phone!,
                    ),
                  if (user?.email != null && user!.email!.isNotEmpty)
                    _InfoTile(
                      icon: Icons.email_outlined,
                      title: 'Email',
                      value: user.email!,
                    ),
                  _InfoTile(
                    icon: Icons.badge_outlined,
                    title: 'Vai trò',
                    value: (session?.roles.isNotEmpty ?? false)
                        ? session!.roles.join(', ')
                        : '—',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          FilledButton.tonalIcon(
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
            label: const Text('Đăng xuất'),
          ),
        ],
      ),
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.title,
    required this.value,
  });

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListTile(
      leading: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: theme.colorScheme.surfaceContainerLow,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Icon(icon, size: 20, color: theme.colorScheme.onSurfaceVariant),
      ),
      title: Text(title),
      subtitle: Text(
        value,
        style: theme.textTheme.bodyMedium?.copyWith(
          color: theme.colorScheme.onSurfaceVariant,
        ),
      ),
    );
  }
}
