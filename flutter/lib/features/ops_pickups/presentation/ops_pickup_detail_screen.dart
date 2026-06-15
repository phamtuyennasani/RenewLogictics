import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/utils/contact_actions.dart';
import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/detail_widgets.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/status_chip.dart';
import '../../shipper_pickup/domain/pickup.dart';
import '../data/ops_pickup_repository.dart';
import 'ops_pickup_list_controller.dart';
import 'ops_pickup_providers.dart';

/// Controller chi tiết pickup OPS.
class OpsPickupDetailController extends StateNotifier<AsyncValue<PickupDetail>> {
  OpsPickupDetailController(this._repo, this.pickupId)
      : super(const AsyncLoading()) {
    _load();
  }

  final OpsPickupRepository _repo;
  final int pickupId;

  Future<void> _load() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() => _repo.detail(pickupId));
  }

  Future<void> refresh() => _load();

  Future<void> assignShipper(int shipperId) async {
    await _repo.assignShipper(pickupId, shipperId);
    await refresh();
  }
}

final opsPickupDetailControllerProvider = StateNotifierProvider.family<
    OpsPickupDetailController, AsyncValue<PickupDetail>, int>(
  (ref, pickupId) {
    final repo = ref.watch(opsPickupRepositoryProvider);
    return OpsPickupDetailController(repo, pickupId);
  },
);

/// Màn chi tiết pickup OPS.
class OpsPickupDetailScreen extends ConsumerWidget {
  const OpsPickupDetailScreen({super.key, required this.pickupId});

  final int pickupId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(opsPickupDetailControllerProvider(pickupId));
    final notifier =
        ref.read(opsPickupDetailControllerProvider(pickupId).notifier);

    return Scaffold(
      appBar: AppBar(
        title: Text(state.valueOrNull?.pickup.maPickup ?? 'Chi tiết pickup'),
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
          child: _DetailContent(detail: detail, pickupId: pickupId),
        ),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) =>
            ErrorState(message: err.toString(), onRetry: notifier.refresh),
      ),
    );
  }
}

class _DetailContent extends ConsumerWidget {
  const _DetailContent({required this.detail, required this.pickupId});

  final PickupDetail detail;
  final int pickupId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pickup = detail.pickup;

    return ListView(
      padding: EdgeInsets.fromLTRB(
        12,
        10,
        12,
        24 + MediaQuery.of(context).padding.bottom,
      ),
      children: [
        DetailHeaderCard(
          icon: Icons.local_shipping_outlined,
          overline: 'Phiếu lấy hàng',
          title: pickup.maPickup,
          trailing: StatusChip(badge: pickup.status, dense: true),
        ),
        const SizedBox(height: 12),
        _CustomerCard(pickup: pickup),
        const SizedBox(height: 12),
        _OverviewCard(detail: detail),
        if (pickup.note != null && pickup.note!.trim().isNotEmpty) ...[
          const SizedBox(height: 12),
          SectionCard(
            icon: Icons.sticky_note_2_outlined,
            title: 'Ghi chú',
            child: Text(
              pickup.note!,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
            ),
          ),
        ],
        if (detail.orders.isNotEmpty) ...[
          const SizedBox(height: 12),
          _OrdersCard(orders: detail.orders),
        ],
        const SizedBox(height: 12),
        _ShipperCard(pickup: pickup, pickupId: pickupId),
      ],
    );
  }
}

class _CustomerCard extends StatelessWidget {
  const _CustomerCard({required this.pickup});

  final Pickup pickup;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final customer = pickup.customer;
    final phone = customer.phone?.trim() ?? '';
    final address = customer.address?.trim() ?? '';
    final hasName =
        customer.fullname != null && customer.fullname!.trim().isNotEmpty;
    final hasCompany =
        customer.company != null && customer.company!.trim().isNotEmpty;

    return SectionCard(
      icon: Icons.business_outlined,
      title: 'Khách hàng',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(customer.displayName, style: theme.textTheme.titleMedium),
          if (hasName && hasCompany) ...[
            const SizedBox(height: 2),
            Text(
              customer.fullname!,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
          if (address.isNotEmpty) ...[
            const SizedBox(height: 10),
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

class _OverviewCard extends StatelessWidget {
  const _OverviewCard({required this.detail});

  final PickupDetail detail;

  @override
  Widget build(BuildContext context) {
    final p = detail.pickup;
    return SectionCard(
      icon: Icons.analytics_outlined,
      title: 'Tổng quan pickup',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InfoTile(
            icon: Icons.inventory_2_outlined,
            label: 'Số kiện',
            value: '${p.packageCount ?? 0}',
          ),
          if (p.ordersCount != null)
            InfoTile(
              icon: Icons.receipt_long_outlined,
              label: 'Số đơn',
              value: '${p.ordersCount}',
            ),
          if (p.weightKg != null)
            InfoTile(
              icon: Icons.scale_outlined,
              label: 'Trọng lượng',
              value: DateFormatters.weight(p.weightKg),
            ),
          if (detail.weightGrossKg != null)
            InfoTile(
              icon: Icons.fitness_center_outlined,
              label: 'Trọng lượng gross',
              value: DateFormatters.weight(detail.weightGrossKg),
            ),
          if (p.scheduledAt != null)
            InfoTile(
              icon: Icons.schedule_outlined,
              label: 'Hẹn lấy',
              value: DateFormatters.dateTime(p.scheduledAt),
            ),
          if (detail.createdAt != null)
            InfoTile(
              icon: Icons.event_outlined,
              label: 'Tạo lúc',
              value: DateFormatters.dateTime(detail.createdAt),
            ),
        ],
      ),
    );
  }
}

class _OrdersCard extends StatelessWidget {
  const _OrdersCard({required this.orders});

  final List<PickupOrderRef> orders;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SectionCard(
      icon: Icons.receipt_long_outlined,
      title: 'Đơn hàng (${orders.length})',
      child: Column(
        children: [
          for (final o in orders)
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
                          o.trackingCode?.trim().isNotEmpty == true
                              ? o.trackingCode!
                              : (o.idBill?.trim().isNotEmpty == true
                                  ? o.idBill!
                                  : 'Đơn #${o.id}'),
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        if (o.idBill?.trim().isNotEmpty == true &&
                            o.trackingCode?.trim().isNotEmpty == true)
                          Text(
                            o.idBill!,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                      ],
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

class _ShipperCard extends ConsumerWidget {
  const _ShipperCard({required this.pickup, required this.pickupId});

  final Pickup pickup;
  final int pickupId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final assigned = pickup.createdBy?.trim();
    final hasShipper = assigned != null && assigned.isNotEmpty;

    return SectionCard(
      icon: Icons.delivery_dining_outlined,
      title: 'Shipper',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 30,
                height: 30,
                decoration: BoxDecoration(
                  color: theme.colorScheme.surfaceContainerLow,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  hasShipper ? Icons.person : Icons.person_off_outlined,
                  size: 16,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  hasShipper ? assigned : 'Chưa gán shipper',
                  style: theme.textTheme.bodyLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: hasShipper ? null : theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: () => _showShipperSheet(context, ref),
              icon: Icon(hasShipper ? Icons.swap_horiz : Icons.person_add,
                  size: 18),
              label: Text(hasShipper ? 'Đổi shipper' : 'Chọn shipper'),
            ),
          ),
        ],
      ),
    );
  }

  void _showShipperSheet(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => _ShipperPickerSheet(
        onSelected: (shipperId) {
          Navigator.of(ctx).pop();
          _assignShipper(context, ref, shipperId);
        },
      ),
    );
  }

  Future<void> _assignShipper(
      BuildContext context, WidgetRef ref, int shipperId) async {
    try {
      await ref
          .read(opsPickupDetailControllerProvider(pickupId).notifier)
          .assignShipper(shipperId);
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã gán shipper thành công')),
      );
      ref.read(opsPickupListControllerProvider.notifier).refresh();
    } catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Lỗi: ${e.toString()}'),
          backgroundColor: Theme.of(context).colorScheme.error,
        ),
      );
    }
  }
}

class _ShipperPickerSheet extends ConsumerStatefulWidget {
  const _ShipperPickerSheet({required this.onSelected});

  final void Function(int shipperId) onSelected;

  @override
  ConsumerState<_ShipperPickerSheet> createState() =>
      _ShipperPickerSheetState();
}

class _ShipperPickerSheetState extends ConsumerState<_ShipperPickerSheet> {
  List<ShipperOption> _shippers = [];
  bool _isLoading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadShippers();
  }

  Future<void> _loadShippers() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final shippers = await ref.read(opsPickupRepositoryProvider).shippers();
      if (mounted) {
        setState(() {
          _shippers = shippers;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _error = 'Không tải được danh sách shipper.';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.7,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
              child: Row(
                children: [
                  Icon(Icons.delivery_dining_outlined,
                      color: theme.colorScheme.primary),
                  const SizedBox(width: 8),
                  Text(
                    'Chọn shipper',
                    style: theme.textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Flexible(child: _buildList(theme)),
          ],
        ),
      ),
    );
  }

  Widget _buildList(ThemeData theme) {
    if (_isLoading) {
      return const Padding(
        padding: EdgeInsets.all(32),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (_error != null) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _loadShippers,
              icon: const Icon(Icons.refresh),
              label: const Text('Thử lại'),
            ),
          ],
        ),
      );
    }
    if (_shippers.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(32),
        child: Center(child: Text('Không có shipper khả dụng.')),
      );
    }
    return ListView.separated(
      shrinkWrap: true,
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: _shippers.length,
      separatorBuilder: (_, _) => const Divider(height: 1, indent: 64),
      itemBuilder: (context, index) {
        final shipper = _shippers[index];
        return ListTile(
          leading: CircleAvatar(
            backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.12),
            child: Icon(Icons.person, color: theme.colorScheme.primary),
          ),
          title: Text(shipper.name),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => widget.onSelected(shipper.id),
        );
      },
    );
  }
}
