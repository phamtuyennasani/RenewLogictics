import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/app_surfaces.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../domain/app_notification.dart';
import 'notifications_controller.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsControllerProvider);
    final notifier = ref.read(notificationsControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Thông báo'),
        actions: [
          IconButton(
            icon: const Icon(Icons.done_all),
            tooltip: 'Đánh dấu tất cả đã đọc',
            onPressed: state.unreadCount > 0
                ? () async {
                    await notifier.markAllRead();
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Đã đánh dấu tất cả đã đọc.'),
                        ),
                      );
                    }
                  }
                : null,
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Tải lại',
            onPressed: notifier.load,
          ),
        ],
      ),
      body: AppPage(child: _buildBody(context, state, notifier)),
    );
  }

  Widget _buildBody(
    BuildContext context,
    NotificationsState state,
    NotificationsController notifier,
  ) {
    if (state.isLoading && state.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.errorMessage != null && state.items.isEmpty) {
      return ErrorState(message: state.errorMessage!, onRetry: notifier.load);
    }

    if (state.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: notifier.load,
        child: ListView(
          padding: EdgeInsets.fromLTRB(
            12,
            12,
            12,
            24 + MediaQuery.paddingOf(context).bottom,
          ),
          children: const [
            SizedBox(height: 160),
            EmptyState(
              icon: Icons.notifications_none_outlined,
              title: 'Chưa có thông báo',
              message: 'Thông báo nội bộ sẽ hiển thị tại đây.',
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: notifier.load,
      child: ListView.separated(
        padding: EdgeInsets.fromLTRB(
          12,
          10,
          12,
          24 + MediaQuery.paddingOf(context).bottom,
        ),
        itemCount: state.items.length + (state.hasMore ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          if (index >= state.items.length) {
            return _LoadMoreButton(state: state, onPressed: notifier.loadMore);
          }
          final item = state.items[index];
          return _NotificationCard(
            item: item,
            onTap: () {
              notifier.markRead(item);
              // Tôn trọng nhánh hiện tại (shipper dùng /shipper/..., OPS dùng
              // /ops/...) để không bị auth guard chặn khi điều hướng.
              final onShipperBranch = GoRouterState.of(
                context,
              ).matchedLocation.startsWith(AppRoutes.shipper);
              context.push(
                AppRoutes.notificationDetailLocation(
                  item.id,
                  ops: !onShipperBranch,
                ),
                extra: item,
              );
            },
          );
        },
      ),
    );
  }
}

class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.item, required this.onTap});

  final AppNotification item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = item.isRead
        ? theme.colorScheme.onSurfaceVariant
        : theme.colorScheme.primary;

    return AppSurface(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: item.isRead ? 0.08 : 0.12),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              item.isRead
                  ? Icons.notifications_none_outlined
                  : Icons.notifications_active_outlined,
              color: accent,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    if (!item.isRead) ...[
                      const SizedBox(width: 8),
                      Container(
                        width: 8,
                        height: 8,
                        decoration: BoxDecoration(
                          color: theme.colorScheme.primary,
                          shape: BoxShape.circle,
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 5),
                Text(
                  item.excerpt,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    height: 1.3,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    _MetaPill(
                      icon: Icons.schedule_outlined,
                      label: DateFormatters.dateTime(item.createdAt),
                    ),
                    _MetaPill(icon: Icons.person_outline, label: item.author),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MetaPill extends StatelessWidget {
  const _MetaPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: theme.colorScheme.outlineVariant),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 4),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _LoadMoreButton extends StatelessWidget {
  const _LoadMoreButton({required this.state, required this.onPressed});

  final NotificationsState state;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: OutlinedButton.icon(
        onPressed: state.isLoadingMore ? null : onPressed,
        icon: state.isLoadingMore
            ? const SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.expand_more),
        label: Text(state.isLoadingMore ? 'Đang tải...' : 'Tải thêm'),
      ),
    );
  }
}
