import 'package:flutter/material.dart';

import '../../../../shared/widgets/status_chip.dart';
import '../../domain/shipper_scan_result.dart';

/// Card hiển thị kết quả quét mã kiện của shipper.
///
/// Ba trạng thái: không tìm thấy pickup, tìm thấy nhưng đã nhận (không nhận
/// lại được), tìm thấy + cho phép nhận hàng (hiện nút "Nhận hàng").
class ShipperScanResultCard extends StatelessWidget {
  const ShipperScanResultCard({
    super.key,
    required this.result,
    required this.isReceiving,
    required this.onReceive,
    required this.onClear,
  });

  final ShipperScanResult result;
  final bool isReceiving;
  final VoidCallback onReceive;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    // Không tìm thấy pickup khớp mã.
    if (!result.found || result.pickup == null) {
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
                    'Không tìm thấy pickup',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    result.reason ??
                        'Không có pickup nào của bạn khớp mã kiện này.',
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

    final pickup = result.pickup!;
    final customer = pickup.customer;
    final canReceive = result.canReceive;

    return _Shell(
      onClear: onClear,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  pickup.maPickup,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    fontFamily: 'monospace',
                  ),
                ),
              ),
              StatusChip(badge: pickup.status, dense: true),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            'Mã kiện: ${result.packageCode}'
            '${result.orderCode != null ? ' · Đơn ${result.orderCode}' : ''}',
            style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor),
          ),
          const Divider(height: 20),
          _InfoRow(label: 'Khách hàng', value: customer.displayName),
          if ((customer.address ?? '').isNotEmpty)
            _InfoRow(label: 'Địa chỉ', value: customer.address!),
          if ((customer.phone ?? '').isNotEmpty)
            _InfoRow(label: 'SĐT', value: customer.phone!),
          if (pickup.packageCount != null)
            _InfoRow(label: 'Số kiện', value: '${pickup.packageCount}'),
          const SizedBox(height: 14),
          if (canReceive)
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: isReceiving ? null : onReceive,
                icon: isReceiving
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.inventory_2_outlined),
                label: Text(isReceiving ? 'Đang nhận...' : 'Nhận hàng'),
                style: FilledButton.styleFrom(
                  backgroundColor: Colors.green.shade600,
                  minimumSize: const Size.fromHeight(48),
                ),
              ),
            )
          else
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: theme.colorScheme.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Icon(Icons.check_circle,
                      color: Colors.green.shade600, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      result.reason ?? 'Pickup này đã được nhận hàng.',
                      style: theme.textTheme.bodyMedium,
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

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

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
          SizedBox(
            width: 84,
            child: Text(
              label,
              style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: theme.textTheme.bodyMedium
                  ?.copyWith(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

/// Khung chung cho card + nút đóng (clear) ở góc phải.
class _Shell extends StatelessWidget {
  const _Shell({required this.child, required this.onClear});

  final Widget child;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: theme.colorScheme.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: theme.colorScheme.outlineVariant),
        ),
        child: Stack(
          children: [
            Padding(padding: const EdgeInsets.all(14), child: child),
            Positioned(
              top: 2,
              right: 2,
              child: IconButton(
                icon: const Icon(Icons.close, size: 20),
                tooltip: 'Đóng',
                onPressed: onClear,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
