import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/app_surfaces.dart';
import '../../../shared/widgets/empty_state.dart';
import '../domain/scan_result.dart';
import 'scan_controller.dart';

/// Màn lịch sử quét trong phiên (contract §4.4 — MVP client-side).
///
/// Hiển thị các mã đã quét/nhập kho gần nhất, mới nhất ở đầu. Cho phép xóa
/// toàn bộ lịch sử phiên hiện tại.
class RecentScansScreen extends ConsumerWidget {
  const RecentScansScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final recent = ref.watch(scanControllerProvider.select((s) => s.recent));
    final theme = Theme.of(context);

    final receivedCount = recent.where((e) => e.received).length;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Lịch sử quét'),
        actions: [
          if (recent.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.delete_outline),
              tooltip: 'Xóa lịch sử',
              onPressed: () => _confirmClear(context, ref),
            ),
        ],
      ),
      body: AppPage(
        child: recent.isEmpty
            ? const EmptyState(
                icon: Icons.history,
                title: 'Chưa có lịch sử',
                message: 'Các mã bạn quét trong phiên này sẽ hiện ở đây.',
              )
            : Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(14, 6, 14, 10),
                    child: AppSurface(
                      padding: const EdgeInsets.all(14),
                      child: Row(
                        children: [
                          Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: theme.colorScheme.primaryContainer
                                  .withValues(alpha: 0.68),
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(
                                color: theme.colorScheme.primary.withValues(
                                  alpha: 0.12,
                                ),
                              ),
                            ),
                            child: Icon(
                              Icons.history,
                              color: theme.colorScheme.primary,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              '${recent.length} lượt quét trong phiên',
                              style: theme.textTheme.titleSmall,
                            ),
                          ),
                          Text(
                            '$receivedCount đã nhập',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.primary,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Expanded(
                    child: ListView.builder(
                      padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
                      itemCount: recent.length,
                      itemBuilder: (context, index) =>
                          _RecentTile(scan: recent[index]),
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  Future<void> _confirmClear(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Xóa lịch sử quét?'),
        content: const Text(
          'Toàn bộ lịch sử quét trong phiên này sẽ bị xóa. '
          'Dữ liệu nhập kho trên hệ thống không bị ảnh hưởng.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('Xóa'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await ref.read(scanControllerProvider.notifier).clearHistory();
    }
  }
}

class _RecentTile extends StatelessWidget {
  const _RecentTile({required this.scan});

  final RecentScan scan;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final received = scan.received;
    final color = received ? const Color(0xFF047857) : theme.colorScheme.error;

    return AppSurface(
      margin: const EdgeInsets.only(bottom: 10),
      padding: EdgeInsets.zero,
      child: ListTile(
        leading: Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: color.withValues(alpha: 0.12)),
          ),
          child: Icon(
            received ? Icons.check_circle_outline : Icons.error_outline,
            color: color,
            size: 22,
          ),
        ),
        title: Text(
          scan.idBill ?? scan.code,
          style: theme.textTheme.bodyLarge?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (scan.idBill != null && scan.idBill != scan.code)
              Text('Mã quét: ${scan.code}', style: theme.textTheme.bodySmall),
            Text(
              received
                  ? (scan.statusLabel ?? 'Đã nhập kho')
                  : (scan.note ?? 'Không nhập kho được'),
              style: theme.textTheme.bodySmall?.copyWith(
                color: color,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        trailing: Text(
          DateFormatters.time(scan.scannedAt),
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        isThreeLine: scan.idBill != null && scan.idBill != scan.code,
      ),
    );
  }
}
