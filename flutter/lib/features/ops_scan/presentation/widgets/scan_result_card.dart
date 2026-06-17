import 'package:flutter/material.dart';

import '../../../../core/utils/date_formatters.dart';
import '../../../../shared/widgets/status_chip.dart';
import '../../domain/scan_result.dart';

/// Card hiển thị kết quả tra cứu một mã quét (contract §4.1).
///
/// Ba trạng thái: không tìm thấy, tìm thấy nhưng không nhập được (kèm lý do),
/// tìm thấy + cho phép nhập kho (hiện nút nhận).
class ScanResultCard extends StatelessWidget {
  const ScanResultCard({
    super.key,
    required this.result,
    required this.isReceiving,
    required this.onReceive,
    required this.onClear,
  });

  final ScanResult result;
  final bool isReceiving;
  final VoidCallback onReceive;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    // Không tìm thấy đơn khớp mã.
    if (!result.found || result.order == null) {
      return _Shell(
        onClear: onClear,
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: theme.colorScheme.errorContainer.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(
                Icons.search_off,
                color: theme.colorScheme.error,
                size: 24,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Không tìm thấy đơn',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    result.reason ?? 'Không có đơn nào khớp mã đã quét.',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.hintColor,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    final order = result.order!;
    final canReceive = result.canReceive;

    return _Shell(
      onClear: onClear,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: theme.colorScheme.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  Icons.inventory_2_outlined,
                  color: theme.colorScheme.primary,
                  size: 22,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  order.primaryCode,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              StatusChip(badge: order.status),
              const SizedBox(width: 34),
            ],
          ),
          if (result.matchedBy != ScanMatchedBy.unknown) ...[
            const SizedBox(height: 2),
            Text(
              'Khớp theo ${result.matchedBy.label}'
              '${result.matchedPackageCode != null ? ' · ${result.matchedPackageCode}' : ''}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.hintColor,
              ),
            ),
          ],
          const Divider(height: 20),
          if (order.sender != null)
            _Row(
              icon: Icons.outbox_outlined,
              label: 'Người gửi',
              value: order.sender!.displayName,
            ),
          if (order.receiver != null)
            _Row(
              icon: Icons.move_to_inbox_outlined,
              label: 'Người nhận',
              value:
                  order.receiver!.displayName +
                  (order.receiver!.country != null
                      ? ' (${order.receiver!.country})'
                      : ''),
            ),
          Wrap(
            spacing: 16,
            runSpacing: 4,
            children: [
              if (order.packageCount != null)
                _Meta(
                  icon: Icons.inventory_2_outlined,
                  text: '${order.packageCount} kiện',
                ),
              if (order.weightKg != null)
                _Meta(
                  icon: Icons.scale_outlined,
                  text: DateFormatters.weight(order.weightKg),
                ),
              if (order.saleName != null && order.saleName!.isNotEmpty)
                _Meta(icon: Icons.person_outline, text: order.saleName!),
            ],
          ),
          if (order.receivedAt != null) ...[
            const SizedBox(height: 6),
            _Meta(
              icon: Icons.event_available_outlined,
              text: 'Đã nhận: ${DateFormatters.dateTime(order.receivedAt)}',
            ),
          ],
          const SizedBox(height: 14),
          if (canReceive && (order.packageCount ?? 1) > 1) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.amber.shade100,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.amber.shade300),
              ),
              child: Row(
                children: [
                  Icon(Icons.warning_amber_rounded,
                      size: 18, color: Colors.amber.shade800),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Đơn hàng có ${order.packageCount} kiện, xác nhận cập nhật?',
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: Colors.amber.shade900,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
          ],
          if (canReceive)
            FilledButton.icon(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
              ),
              onPressed: isReceiving ? null : onReceive,
              icon: isReceiving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.check_circle_outline),
              label: Text(isReceiving ? 'Đang nhập kho…' : 'Xác nhận nhập kho'),
            )
          else
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: theme.colorScheme.errorContainer.withValues(alpha: 0.4),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: theme.colorScheme.error.withValues(alpha: 0.12),
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.info_outline,
                    size: 18,
                    color: theme.colorScheme.error,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      result.reason ??
                          'Đơn không ở trạng thái cho phép nhập kho.',
                      style: theme.textTheme.bodySmall,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _Shell extends StatelessWidget {
  const _Shell({required this.child, required this.onClear});

  final Widget child;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 10),
      child: Stack(
        children: [
          Padding(padding: const EdgeInsets.all(16), child: child),
          Positioned(
            top: 6,
            right: 6,
            child: IconButton(
              icon: const Icon(Icons.close, size: 18),
              tooltip: 'Đóng',
              onPressed: onClear,
            ),
          ),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainerLow,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(
              icon,
              size: 15,
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(width: 8),
          SizedBox(
            width: 80,
            child: Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
          Expanded(child: Text(value, style: theme.textTheme.bodyMedium)),
        ],
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: theme.colorScheme.outlineVariant),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 4),
          Text(
            text,
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
