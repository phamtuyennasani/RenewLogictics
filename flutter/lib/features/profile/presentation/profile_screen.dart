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
        padding: const EdgeInsets.all(16),
        children: [
          Center(
            child: Column(
              children: [
                CircleAvatar(
                  radius: 40,
                  backgroundColor: theme.colorScheme.primaryContainer,
                  backgroundImage:
                      (user?.avatar != null && user!.avatar!.isNotEmpty)
                          ? NetworkImage(user.avatar!)
                          : null,
                  child: (user?.avatar == null || user!.avatar!.isEmpty)
                      ? const Icon(Icons.person, size: 40)
                      : null,
                ),
                const SizedBox(height: 12),
                Text(
                  user?.fullname.isNotEmpty == true
                      ? user!.fullname
                      : (user?.username ?? '—'),
                  style: theme.textTheme.titleLarge,
                ),
                if (user?.username != null)
                  Text('@${user!.username}',
                      style: theme.textTheme.bodyMedium
                          ?.copyWith(color: theme.hintColor)),
              ],
            ),
          ),
          const SizedBox(height: 24),
          if (user?.phone != null && user!.phone!.isNotEmpty)
            ListTile(
              leading: const Icon(Icons.phone_outlined),
              title: const Text('Điện thoại'),
              subtitle: Text(user.phone!),
            ),
          if (user?.email != null && user!.email!.isNotEmpty)
            ListTile(
              leading: const Icon(Icons.email_outlined),
              title: const Text('Email'),
              subtitle: Text(user.email!),
            ),
          ListTile(
            leading: const Icon(Icons.badge_outlined),
            title: const Text('Vai trò'),
            subtitle: Text(
              (session?.roles.isNotEmpty ?? false)
                  ? session!.roles.join(', ')
                  : '—',
            ),
          ),
          const SizedBox(height: 24),
          FilledButton.tonalIcon(
            onPressed: () =>
                ref.read(authControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
            label: const Text('Đăng xuất'),
          ),
        ],
      ),
    );
  }
}
