import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/utils/contact_actions.dart';
import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/app_surfaces.dart';
import '../../../shared/widgets/app_toast.dart';
import '../../../shared/widgets/detail_widgets.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/status_chip.dart';
import '../../shipper_pickup/domain/pickup.dart';
import '../../shipper_pickup/domain/pickup_image.dart';
import '../data/ops_pickup_repository.dart';
import 'ops_pickup_list_controller.dart';
import 'ops_pickup_providers.dart';

/// Controller chi tiết pickup OPS.
class OpsPickupDetailController
    extends StateNotifier<AsyncValue<PickupDetail>> {
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

final opsPickupDetailControllerProvider =
    StateNotifierProvider.family<
      OpsPickupDetailController,
      AsyncValue<PickupDetail>,
      int
    >((ref, pickupId) {
      final repo = ref.watch(opsPickupRepositoryProvider);
      return OpsPickupDetailController(repo, pickupId);
    });

/// Màn chi tiết pickup OPS.
class OpsPickupDetailScreen extends ConsumerWidget {
  const OpsPickupDetailScreen({super.key, required this.pickupId});

  final int pickupId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(opsPickupDetailControllerProvider(pickupId));
    final notifier = ref.read(
      opsPickupDetailControllerProvider(pickupId).notifier,
    );

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
      body: AppPage(
        child: state.when(
          data: (detail) => RefreshIndicator(
            onRefresh: notifier.refresh,
            child: _DetailContent(detail: detail, pickupId: pickupId),
          ),
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (err, _) =>
              ErrorState(message: err.toString(), onRetry: notifier.refresh),
        ),
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
        _StatusHeader(detail: detail),
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
        _ImagesCard(images: detail.images),
        const SizedBox(height: 12),
        _ShipperCard(pickup: pickup, pickupId: pickupId),
      ],
    );
  }
}

class _StatusHeader extends StatelessWidget {
  const _StatusHeader({required this.detail});

  final PickupDetail detail;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final pickup = detail.pickup;
    final assigned = pickup.shipper?.name.trim();
    final hasShipper = assigned != null && assigned.isNotEmpty;

    return AppHeroPanel(
      trailingIcon: Icons.inventory_2_outlined,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.local_shipping_outlined,
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
                            'Phiếu pickup OPS',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.76),
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
                    const SizedBox(height: 4),
                    Text(
                      pickup.customer.displayName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: Colors.white.withValues(alpha: 0.86),
                        fontWeight: FontWeight.w600,
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
              _HeaderMetric(
                icon: Icons.inventory_2_outlined,
                label: '${pickup.packageCount ?? 0} kiện',
              ),
              if (pickup.ordersCount != null)
                _HeaderMetric(
                  icon: Icons.receipt_long_outlined,
                  label: '${pickup.ordersCount} đơn',
                ),
              if (pickup.weightKg != null)
                _HeaderMetric(
                  icon: Icons.scale_outlined,
                  label: DateFormatters.weight(pickup.weightKg),
                ),
              _HeaderMetric(
                icon: hasShipper ? Icons.person : Icons.person_off_outlined,
                label: hasShipper ? assigned : 'Chưa gán shipper',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeaderMetric extends StatelessWidget {
  const _HeaderMetric({required this.icon, required this.label});

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

    return AppSurface(
      padding: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    Icons.business_outlined,
                    color: theme.colorScheme.primary,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Khách hàng',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 1),
                      Text(
                        customer.displayName,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (hasName && hasCompany) ...[
              const SizedBox(height: 10),
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
            if (phone.isNotEmpty || pickup.location.hasLocation) ...[
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
                  if (phone.isNotEmpty && pickup.location.hasLocation)
                    const SizedBox(width: 10),
                  if (pickup.location.hasLocation)
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: () => _openMap(context),
                        icon: const Icon(Icons.map_outlined, size: 18),
                        label: const Text('Bản đồ'),
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

  Future<void> _openMap(BuildContext context) async {
    final location = pickup.location;
    if (!location.hasLocation || location.lat == null || location.lng == null) {
      return;
    }
    final ok = await ContactActions.openMap(
      lat: location.lat!,
      lng: location.lng!,
      label: pickup.customer.address,
    );
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Không mở được ứng dụng bản đồ.')),
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
    final items = <_MetricData>[
      _MetricData(
        icon: Icons.inventory_2_outlined,
        label: 'Số kiện',
        value: '${p.packageCount ?? 0}',
      ),
      if (p.ordersCount != null)
        _MetricData(
          icon: Icons.receipt_long_outlined,
          label: 'Số đơn',
          value: '${p.ordersCount}',
        ),
      if (p.weightKg != null)
        _MetricData(
          icon: Icons.scale_outlined,
          label: 'Trọng lượng',
          value: DateFormatters.weight(p.weightKg),
        ),
      if (detail.weightGrossKg != null)
        _MetricData(
          icon: Icons.fitness_center_outlined,
          label: 'Gross',
          value: DateFormatters.weight(detail.weightGrossKg),
        ),
      if (p.scheduledAt != null)
        _MetricData(
          icon: Icons.schedule_outlined,
          label: 'Hẹn lấy',
          value: DateFormatters.dateTime(p.scheduledAt),
        ),
      if (detail.createdAt != null)
        _MetricData(
          icon: Icons.event_outlined,
          label: 'Tạo lúc',
          value: DateFormatters.dateTime(detail.createdAt),
        ),
    ];

    return AppSurface(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionHeading(
            icon: Icons.analytics_outlined,
            title: 'Tổng quan',
            trailing: Text(
              p.status.label,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) {
              final width = (constraints.maxWidth - 10) / 2;
              return Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  for (final item in items)
                    SizedBox(
                      width: width,
                      child: _MetricTile(data: item),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _MetricData {
  const _MetricData({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({required this.data});

  final _MetricData data;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      constraints: const BoxConstraints(minHeight: 82),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: theme.colorScheme.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(data.icon, size: 18, color: theme.colorScheme.primary),
          const SizedBox(height: 8),
          Text(
            data.label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            data.value,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({
    required this.icon,
    required this.title,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        Icon(icon, size: 18, color: theme.colorScheme.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            title,
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        ?trailing,
      ],
    );
  }
}

class _OrdersCard extends StatelessWidget {
  const _OrdersCard({required this.orders});

  final List<PickupOrderRef> orders;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AppSurface(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionHeading(
            icon: Icons.receipt_long_outlined,
            title: 'Đơn hàng',
            trailing: MetaChip(
              icon: Icons.inventory_2_outlined,
              label: '${orders.length} đơn',
            ),
          ),
          const SizedBox(height: 10),
          for (var index = 0; index < orders.length; index++) ...[
            _OrderRow(order: orders[index]),
            if (index != orders.length - 1)
              Divider(height: 14, color: theme.colorScheme.outlineVariant),
          ],
        ],
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
    final subtitle =
        order.idBill?.trim().isNotEmpty == true &&
            order.trackingCode?.trim().isNotEmpty == true
        ? order.idBill!
        : null;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: theme.colorScheme.primary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              Icons.qr_code_2_outlined,
              size: 17,
              color: theme.colorScheme.primary,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (subtitle != null)
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                      fontWeight: FontWeight.w600,
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

class _ImagesCard extends StatelessWidget {
  const _ImagesCard({required this.images});

  final List<PickupImage> images;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AppSurface(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionHeading(
            icon: Icons.photo_camera_back_outlined,
            title: 'Ảnh pickup',
            trailing: MetaChip(
              icon: Icons.photo_library_outlined,
              label: '${images.length} ảnh',
            ),
          ),
          const SizedBox(height: 10),
          if (images.isEmpty)
            Text(
              'Chưa có ảnh bằng chứng.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                fontWeight: FontWeight.w600,
              ),
            )
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final image in images)
                  _ImageThumb(
                    image: image,
                    onTap: () => _openViewer(context, image),
                  ),
              ],
            ),
        ],
      ),
    );
  }

  void _openViewer(BuildContext context, PickupImage image) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        insetPadding: const EdgeInsets.all(12),
        child: GestureDetector(
          onTap: () => Navigator.pop(dialogContext),
          child: InteractiveViewer(
            child: Image.network(
              image.url,
              fit: BoxFit.contain,
              errorBuilder: (_, _, _) => const Padding(
                padding: EdgeInsets.all(32),
                child: Icon(Icons.broken_image_outlined, size: 48),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ImageThumb extends StatelessWidget {
  const _ImageThumb({required this.image, required this.onTap});

  final PickupImage image;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    const size = 88.0;

    return SizedBox(
      width: size,
      height: size,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: GestureDetector(
          onTap: onTap,
          child: Image.network(
            image.url,
            fit: BoxFit.cover,
            loadingBuilder: (_, child, progress) => progress == null
                ? child
                : Container(
                    color: theme.colorScheme.surfaceContainerLow,
                    child: const Center(
                      child: SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    ),
                  ),
            errorBuilder: (_, _, _) => Container(
              color: theme.colorScheme.surfaceContainerLow,
              child: Icon(
                Icons.broken_image_outlined,
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        ),
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
    final assigned = pickup.shipper?.name.trim();
    final hasShipper = assigned != null && assigned.isNotEmpty;
    final isPickedUp = pickup.status.value == 'pickup_da_lay';

    return AppSurface(
      padding: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionHeading(
              icon: Icons.delivery_dining_outlined,
              title: 'Điều phối shipper',
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: hasShipper
                        ? theme.colorScheme.primary.withValues(alpha: 0.1)
                        : theme.colorScheme.errorContainer.withValues(
                            alpha: 0.5,
                          ),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(
                    hasShipper ? Icons.person : Icons.person_off_outlined,
                    size: 20,
                    color: hasShipper
                        ? theme.colorScheme.primary
                        : theme.colorScheme.error,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        hasShipper ? assigned : 'Chưa gán shipper',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                          color: hasShipper ? null : theme.colorScheme.error,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        isPickedUp
                            ? 'Pickup đã lấy, không thể đổi shipper.'
                            : hasShipper
                            ? 'Có thể đổi shipper khi cần điều phối lại.'
                            : 'Cần chọn shipper để tiếp tục xử lý pickup.',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (!isPickedUp) ...[
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => _showShipperSheet(context, ref),
                  icon: Icon(
                    hasShipper ? Icons.swap_horiz : Icons.person_add,
                    size: 18,
                  ),
                  label: Text(hasShipper ? 'Đổi shipper' : 'Chọn shipper'),
                ),
              ),
            ],
          ],
        ),
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
    BuildContext context,
    WidgetRef ref,
    int shipperId,
  ) async {
    try {
      await ref
          .read(opsPickupDetailControllerProvider(pickupId).notifier)
          .assignShipper(shipperId);
      if (!context.mounted) return;
      AppToast.success(
        context,
        'Đã cập nhật người phụ trách pickup.',
        title: 'Đã gán shipper',
      );
      ref.read(opsPickupListControllerProvider.notifier).refresh();
    } catch (e) {
      if (!context.mounted) return;
      AppToast.error(context, _messageOf(e), title: 'Không thể gán shipper');
    }
  }

  String _messageOf(Object error) {
    if (error is ApiException) {
      return error.message;
    }
    return 'Thao tác thất bại. Vui lòng thử lại.';
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
                  Icon(
                    Icons.delivery_dining_outlined,
                    color: theme.colorScheme.primary,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'Chọn shipper',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
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
