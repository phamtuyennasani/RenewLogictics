import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/utils/contact_actions.dart';
import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/detail_widgets.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/status_chip.dart';
import '../domain/ops_order.dart';
import '../domain/ops_order_repository.dart';
import 'ops_order_providers.dart';

/// Controller chi tiết order OPS.
class OpsOrderDetailController extends StateNotifier<AsyncValue<OpsOrderDetail>> {
  OpsOrderDetailController(this._repo, this.orderId) : super(const AsyncLoading()) {
    _load();
  }

  final OpsOrderRepository _repo;
  final int orderId;

  Future<void> _load() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => _repo.detail(orderId));
  }

  Future<void> refresh() => _load();
}

final opsOrderDetailControllerProvider = StateNotifierProvider.family<
    OpsOrderDetailController, AsyncValue<OpsOrderDetail>, int>(
  (ref, orderId) {
    final repo = ref.watch(opsOrderRepositoryProvider);
    return OpsOrderDetailController(repo, orderId);
  },
);

/// Màn chi tiết order OPS.
class OpsOrderDetailScreen extends ConsumerWidget {
  const OpsOrderDetailScreen({super.key, required this.orderId});

  final int orderId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(opsOrderDetailControllerProvider(orderId));
    final notifier =
        ref.read(opsOrderDetailControllerProvider(orderId).notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chi tiết đơn hàng'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Tải lại',
            onPressed: notifier.refresh,
          ),
        ],
      ),
      body: state.when(
        data: (detail) => RefreshIndicator(
          onRefresh: notifier.refresh,
          child: _DetailContent(detail: detail),
        ),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => ErrorState(
          message: err.toString(),
          onRetry: notifier.refresh,
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({required this.detail});

  final OpsOrderDetail detail;

  @override
  Widget build(BuildContext context) {
    final order = detail.order;
    final sender = order.sender;

    return ListView(
      padding: EdgeInsets.fromLTRB(
        12,
        10,
        12,
        24 + MediaQuery.of(context).padding.bottom,
      ),
      children: [
        DetailHeaderCard(
          icon: Icons.receipt_long_outlined,
          overline: 'Đơn hàng',
          title: order.idBill,
          subtitle: order.trackingCode,
          trailing: StatusChip(badge: order.status, dense: true),
        ),
        const SizedBox(height: 12),
        SectionCard(
          icon: Icons.analytics_outlined,
          title: 'Thông tin đơn',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              InfoTile(
                icon: Icons.confirmation_number_outlined,
                label: 'Mã đơn',
                value: order.idBill,
              ),
              if (order.trackingCode != null &&
                  order.trackingCode!.trim().isNotEmpty)
                InfoTile(
                  icon: Icons.qr_code_2_outlined,
                  label: 'Tracking',
                  value: order.trackingCode!,
                ),
              if (order.mathamchieu != null &&
                  order.mathamchieu!.trim().isNotEmpty)
                InfoTile(
                  icon: Icons.tag_outlined,
                  label: 'Mã tham chiếu',
                  value: order.mathamchieu!,
                ),
              InfoTile(
                icon: Icons.inventory_2_outlined,
                label: 'Số kiện',
                value: '${order.packageCount ?? detail.packages.length}',
              ),
              if (order.weightKg != null)
                InfoTile(
                  icon: Icons.scale_outlined,
                  label: 'Trọng lượng',
                  value: DateFormatters.weight(order.weightKg),
                ),
              if (order.saleName != null && order.saleName!.trim().isNotEmpty)
                InfoTile(
                  icon: Icons.support_agent_outlined,
                  label: 'Sale',
                  value: order.saleName!,
                ),
              if (order.createdAt != null)
                InfoTile(
                  icon: Icons.event_outlined,
                  label: 'Ngày tạo',
                  value: DateFormatters.dateTime(order.createdAt),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _SenderCard(sender: sender),
        if (detail.note != null && detail.note!.trim().isNotEmpty) ...[
          const SizedBox(height: 12),
          SectionCard(
            icon: Icons.sticky_note_2_outlined,
            title: 'Ghi chú',
            child: Text(
              detail.note!,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
          ),
        ],
        if (detail.packages.isNotEmpty) ...[
          const SizedBox(height: 12),
          _PackagesCard(packages: detail.packages),
        ],
        const SizedBox(height: 20),
        _PickupAction(detail: detail),
      ],
    );
  }
}

class _SenderCard extends StatelessWidget {
  const _SenderCard({required this.sender});

  final OrderSender sender;

  @override
  Widget build(BuildContext context) {
    final phone = sender.phone?.trim() ?? '';
    final hasName =
        sender.fullname != null && sender.fullname!.trim().isNotEmpty;
    final hasCompany =
        sender.company != null && sender.company!.trim().isNotEmpty;

    return SectionCard(
      icon: Icons.business_outlined,
      title: 'Người gửi',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            sender.displayName,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          if (hasName && hasCompany) ...[
            const SizedBox(height: 2),
            Text(
              sender.fullname!,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
          ],
          if (sender.address != null && sender.address!.trim().isNotEmpty) ...[
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.location_on_outlined,
                  size: 18,
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    sender.address!,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ],
            ),
          ],
          if (phone.isNotEmpty) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => _call(context, phone),
                icon: const Icon(Icons.phone_outlined, size: 18),
                label: Text('Gọi $phone'),
              ),
            ),
          ],
        ],
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
}

class _PackagesCard extends StatelessWidget {
  const _PackagesCard({required this.packages});

  final List<OrderPackage> packages;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final totalWeight =
        packages.fold<double>(0, (sum, p) => sum + p.cWeight);

    return SectionCard(
      icon: Icons.inventory_2_outlined,
      title: 'Kiện hàng (${packages.length})',
      trailing: Text(
        DateFormatters.weight(totalWeight),
        style: theme.textTheme.bodySmall?.copyWith(
          fontWeight: FontWeight.w800,
          color: theme.colorScheme.primary,
        ),
      ),
      child: Column(
        children: [
          for (final pkg in packages)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
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
                      Icons.inventory_2_outlined,
                      size: 16,
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Kiện ${pkg.numberOfPackage}',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  Text(
                    DateFormatters.weight(pkg.cWeight),
                    style: theme.textTheme.bodyMedium?.copyWith(
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

class _PickupAction extends StatelessWidget {
  const _PickupAction({required this.detail});

  final OpsOrderDetail detail;

  @override
  Widget build(BuildContext context) {
    if (detail.canCreatePickup) {
      return SizedBox(
        width: double.infinity,
        child: FilledButton.icon(
          onPressed: () => context.push(
            '/ops/orders/${detail.order.id}/create-pickup',
            extra: detail,
          ),
          icon: const Icon(Icons.add_box_outlined),
          label: const Text('Tạo phiếu pickup'),
          style: FilledButton.styleFrom(
            minimumSize: const Size.fromHeight(50),
          ),
        ),
      );
    }

    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.green.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.green.withValues(alpha: 0.28)),
      ),
      child: Row(
        children: [
          const Icon(Icons.check_circle_outline, color: Colors.green),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Đơn hàng đã có phiếu pickup.',
              style: theme.textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
