import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/app_surfaces.dart';
import '../domain/app_notification.dart';
import 'notifications_providers.dart';

class NotificationDetailScreen extends ConsumerStatefulWidget {
  const NotificationDetailScreen({
    super.key,
    this.notification,
    this.notificationId,
  });

  /// Object có sẵn khi điều hướng từ danh sách (extra).
  final AppNotification? notification;

  /// Id khi mở từ push notification (không có sẵn object) → tự fetch.
  final int? notificationId;

  @override
  ConsumerState<NotificationDetailScreen> createState() =>
      _NotificationDetailScreenState();
}

class _NotificationDetailScreenState
    extends ConsumerState<NotificationDetailScreen> {
  AppNotification? _item;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _item = widget.notification;

    // Mở từ push: chỉ có id → fetch chi tiết và đánh dấu đã đọc.
    if (_item == null && widget.notificationId != null) {
      _fetch();
    }
  }

  Future<void> _fetch() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final repo = ref.read(notificationsRepositoryProvider);
    try {
      final item = await repo.getById(widget.notificationId!);
      if (!mounted) return;
      setState(() {
        _item = item;
        _loading = false;
      });
      // Đánh dấu đã đọc (best-effort, không chặn UI).
      if (!item.isRead) {
        repo.markRead(item.id).ignore();
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Không tải được thông báo. Vui lòng thử lại.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = _item;

    if (item == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Chi tiết thông báo')),
        body: AppPage(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: _loading
                  ? const CircularProgressIndicator()
                  : Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          _error ??
                              'Không tìm thấy dữ liệu thông báo. Vui lòng tải lại.',
                          textAlign: TextAlign.center,
                        ),
                        if (widget.notificationId != null) ...[
                          const SizedBox(height: 16),
                          FilledButton(
                            onPressed: _fetch,
                            child: const Text('Thử lại'),
                          ),
                        ],
                      ],
                    ),
            ),
          ),
        ),
      );
    }

    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Chi tiết thông báo')),
      body: AppPage(
        child: ListView(
          padding: EdgeInsets.fromLTRB(
            12,
            10,
            12,
            24 + MediaQuery.paddingOf(context).bottom,
          ),
          children: [
            AppHeroPanel(
              trailingIcon: Icons.notifications_active_outlined,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 50,
                        height: 50,
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.16),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: const Icon(
                          Icons.notifications_active_outlined,
                          color: Colors.white,
                          size: 28,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Thông báo nội bộ',
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: Colors.white.withValues(alpha: 0.76),
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              item.title,
                              style: theme.textTheme.titleLarge?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                height: 1.16,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _HeaderPill(
                        icon: Icons.schedule_outlined,
                        label: DateFormatters.dateTime(item.createdAt),
                      ),
                      _HeaderPill(
                        icon: Icons.person_outline,
                        label: item.author,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            AppSurface(
              padding: const EdgeInsets.fromLTRB(18, 18, 18, 20),
              child: SelectableText(
                item.content.isNotEmpty ? item.content : item.excerpt,
                style: theme.textTheme.bodyLarge?.copyWith(
                  height: 1.55,
                  color: theme.colorScheme.onSurface,
                  letterSpacing: 0,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HeaderPill extends StatelessWidget {
  const _HeaderPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.13),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white.withValues(alpha: 0.22)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: Colors.white),
          const SizedBox(width: 4),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}
