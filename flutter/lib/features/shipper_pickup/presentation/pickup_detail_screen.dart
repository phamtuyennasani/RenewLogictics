import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/utils/contact_actions.dart';
import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/status_chip.dart';
import '../domain/pickup.dart';
import 'pickup_detail_controller.dart';
import 'widgets/status_action_sheet.dart';

/// Màn chi tiết một pickup (contract §3.2) + đổi trạng thái (§3.3).
///
/// Nút đổi trạng thái render từ `allowed_transitions` do API trả (không hardcode
/// FSM). Khi hủy thì bottom sheet bắt nhập lý do.
class PickupDetailScreen extends ConsumerWidget {
  const PickupDetailScreen({super.key, required this.pickupId});

  final int pickupId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(pickupDetailControllerProvider(pickupId));
    final notifier = ref.read(
      pickupDetailControllerProvider(pickupId).notifier,
    );

    // Hiện SnackBar cho thông báo thành công/lỗi rồi clear.
    ref.listen<PickupDetailState>(pickupDetailControllerProvider(pickupId), (
      prev,
      next,
    ) {
      final messenger = ScaffoldMessenger.of(context);
      if (next.actionMessage != null &&
          next.actionMessage != prev?.actionMessage) {
        messenger.showSnackBar(SnackBar(content: Text(next.actionMessage!)));
        notifier.clearMessages();
      } else if (next.errorMessage != null &&
          next.errorMessage != prev?.errorMessage &&
          next.detail != null) {
        // Lỗi khi đã có dữ liệu (vd đổi trạng thái thất bại) → SnackBar.
        messenger.showSnackBar(
          SnackBar(
            content: Text(next.errorMessage!),
            backgroundColor: Theme.of(context).colorScheme.error,
          ),
        );
        notifier.clearMessages();
      }
    });

    return Scaffold(
      appBar: AppBar(
        leading: Navigator.of(context).canPop()
            ? null
            : IconButton(
                icon: const Icon(Icons.arrow_back),
                tooltip: 'Quay lại',
                onPressed: () => context.go(AppRoutes.shipper),
              ),
        title: Text(state.detail?.pickup.maPickup ?? 'Chi tiết pickup'),
      ),
      body: _buildBody(context, ref, state, notifier),
      bottomNavigationBar: _buildActionBar(context, ref, state, notifier),
    );
  }

  Widget _buildBody(
    BuildContext context,
    WidgetRef ref,
    PickupDetailState state,
    PickupDetailController notifier,
  ) {
    if (state.isLoading && state.detail == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (state.errorMessage != null && state.detail == null) {
      return ErrorState(message: state.errorMessage!, onRetry: notifier.load);
    }
    final detail = state.detail;
    if (detail == null) {
      return const SizedBox.shrink();
    }

    return RefreshIndicator(
      onRefresh: notifier.load,
      child: ListView(
        padding: EdgeInsets.fromLTRB(
          12,
          8,
          12,
          24 + MediaQuery.of(context).padding.bottom,
        ),
        children: [
          _StatusHeader(pickup: detail.pickup),
          const SizedBox(height: 12),
          _CustomerCard(
            customer: detail.pickup.customer,
            location: detail.pickup.location,
          ),
          const SizedBox(height: 12),
          _InfoCard(detail: detail),
          if (detail.pickup.note != null &&
              detail.pickup.note!.trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            _NoteCard(note: detail.pickup.note!),
          ],
          if (detail.orders.isNotEmpty) ...[
            const SizedBox(height: 12),
            _OrdersCard(orders: detail.orders),
          ],
        ],
      ),
    );
  }

  Widget? _buildActionBar(
    BuildContext context,
    WidgetRef ref,
    PickupDetailState state,
    PickupDetailController notifier,
  ) {
    final detail = state.detail;
    if (detail == null) return null;
    final transitions = detail.pickup.allowedTransitions;
    if (transitions.isEmpty) return null;

    final theme = Theme.of(context);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        border: Border(
          top: BorderSide(color: theme.colorScheme.outlineVariant),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 18,
            offset: const Offset(0, -8),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
          child: FilledButton.icon(
            onPressed: state.isSubmitting
                ? null
                : () => _openStatusSheet(context, transitions, notifier),
            icon: state.isSubmitting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.sync_alt),
            label: Text(
              state.isSubmitting ? 'Đang cập nhật...' : 'Cập nhật trạng thái',
            ),
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(50),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openStatusSheet(
    BuildContext context,
    List transitions,
    PickupDetailController notifier,
  ) async {
    final choice = await StatusActionSheet.show(
      context,
      transitions: transitions.cast(),
    );
    if (choice == null) return;
    await notifier.changeStatus(status: choice.status, reason: choice.reason);
  }
}

class _StatusHeader extends StatelessWidget {
  const _StatusHeader({required this.pickup});

  final Pickup pickup;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.primary,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(
                Icons.inventory_2_outlined,
                color: Colors.white,
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
                          'Phiếu lấy hàng',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: Colors.white.withValues(alpha: 0.74),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      StatusChip(badge: pickup.status, dense: true),
                    ],
                  ),
                  const SizedBox(height: 4),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text(
                      pickup.maPickup,
                      style: theme.textTheme.headlineSmall?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CardTitle extends StatelessWidget {
  const _CardTitle({required this.icon, required this.title});

  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        Icon(icon, size: 18, color: theme.colorScheme.primary),
        const SizedBox(width: 8),
        Text(
          title,
          style: theme.textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}

class _CustomerCard extends StatelessWidget {
  const _CustomerCard({required this.customer, required this.location});

  final PickupCustomer customer;
  final PickupLocation location;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final phone = customer.phone?.trim() ?? '';
    final address = customer.address?.trim() ?? '';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _CardTitle(
              icon: Icons.business_outlined,
              title: 'Thông tin khách hàng',
            ),
            const SizedBox(height: 14),
            Text(customer.displayName, style: theme.textTheme.titleMedium),
            if (customer.fullname != null &&
                customer.fullname!.trim().isNotEmpty &&
                customer.company != null &&
                customer.company!.trim().isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                customer.fullname!,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
            if (address.isNotEmpty) ...[
              const SizedBox(height: 12),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.location_on_outlined,
                    size: 18,
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(address, style: theme.textTheme.bodyMedium),
                  ),
                ],
              ),
            ],
            if (phone.isNotEmpty || location.hasLocation) ...[
              const SizedBox(height: 14),
              Row(
                children: [
                  if (phone.isNotEmpty)
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _call(context, phone),
                        icon: const Icon(Icons.phone_outlined, size: 18),
                        label: const Text('Gọi'),
                      ),
                    ),
                  if (phone.isNotEmpty && location.hasLocation)
                    const SizedBox(width: 10),
                  if (location.hasLocation)
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _openMap(context, customer),
                        icon: const Icon(Icons.directions_outlined, size: 18),
                        label: const Text('Chỉ đường'),
                      ),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _call(BuildContext context, String phone) async {
    final ok = await ContactActions.call(phone);
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Không mở được ứng dụng gọi điện.')),
      );
    }
  }

  Future<void> _openMap(BuildContext context, PickupCustomer customer) async {
    if (!location.hasLocation || location.lat == null || location.lng == null) {
      return;
    }
    final ok = await ContactActions.openMap(
      lat: location.lat!,
      lng: location.lng!,
      label: customer.address,
    );
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Không mở được bản đồ.')));
    }
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.detail});

  final PickupDetail detail;

  @override
  Widget build(BuildContext context) {
    final p = detail.pickup;
    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _CardTitle(
              icon: Icons.analytics_outlined,
              title: 'Tổng quan pickup',
            ),
            const SizedBox(height: 8),
            _InfoRow(
              icon: Icons.inventory_2_outlined,
              label: 'Số kiện',
              value: '${p.packageCount ?? 0}',
            ),
            if (p.ordersCount != null)
              _InfoRow(
                icon: Icons.receipt_long_outlined,
                label: 'Số đơn',
                value: '${p.ordersCount}',
              ),
            if (p.weightKg != null)
              _InfoRow(
                icon: Icons.scale_outlined,
                label: 'Trọng lượng',
                value: DateFormatters.weight(p.weightKg),
              ),
            if (detail.weightGrossKg != null)
              _InfoRow(
                icon: Icons.fitness_center_outlined,
                label: 'Trọng lượng gross',
                value: DateFormatters.weight(detail.weightGrossKg),
              ),
            if (p.scheduledAt != null)
              _InfoRow(
                icon: Icons.schedule_outlined,
                label: 'Hẹn lấy',
                value: DateFormatters.dateTime(p.scheduledAt),
              ),
            if (detail.createdAt != null)
              _InfoRow(
                icon: Icons.event_outlined,
                label: 'Tạo lúc',
                value: DateFormatters.dateTime(detail.createdAt),
              ),
            if (p.createdBy != null && p.createdBy!.trim().isNotEmpty)
              _InfoRow(
                icon: Icons.person_outline,
                label: 'Người tạo',
                value: p.createdBy!,
              ),
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainerLow,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(
              icon,
              size: 16,
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    fontWeight: FontWeight.w800,
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

class _NoteCard extends StatelessWidget {
  const _NoteCard({required this.note});

  final String note;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const _CardTitle(
              icon: Icons.sticky_note_2_outlined,
              title: 'Ghi chú',
            ),
            const SizedBox(height: 10),
            Text(
              note,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OrdersCard extends StatelessWidget {
  const _OrdersCard({required this.orders});

  final List<PickupOrderRef> orders;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _CardTitle(
              icon: Icons.receipt_long_outlined,
              title: 'Đơn hàng (${orders.length})',
            ),
            const SizedBox(height: 8),
            for (final o in orders) _OrderRow(order: o),
          ],
        ),
      ),
    );
  }
}

class _OrderRow extends StatelessWidget {
  const _OrderRow({required this.order});

  final PickupOrderRef order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = order.trackingCode?.trim().isNotEmpty == true
        ? order.trackingCode!
        : (order.idBill?.trim().isNotEmpty == true
              ? order.idBill!
              : 'Đơn #${order.id}');
    final sub =
        order.idBill?.trim().isNotEmpty == true &&
            order.trackingCode?.trim().isNotEmpty == true
        ? order.idBill
        : null;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainerLow,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(
              Icons.qr_code_2_outlined,
              size: 16,
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (sub != null)
                  Text(
                    sub,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
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
