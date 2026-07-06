import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/router.dart';
import '../../../core/utils/contact_actions.dart';
import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/app_surfaces.dart';
import '../../../shared/widgets/app_toast.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/status_chip.dart';
import '../domain/pickup.dart';
import '../domain/pickup_image.dart';
import 'pickup_detail_controller.dart';
import 'pickup_images_controller.dart';
import 'pickup_list_controller.dart';
import 'pickup_providers.dart';
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
        // Lỗi khi đã có dữ liệu (vd đổi trạng thái / GPS thất bại) → SnackBar.
        // Lỗi vị trí cần cấp quyền → kèm action mở Cài đặt.
        messenger.showSnackBar(
          SnackBar(
            content: Text(next.errorMessage!),
            backgroundColor: Theme.of(context).colorScheme.error,
            action: next.errorOpenSettings
                ? SnackBarAction(
                    label: 'Mở Cài đặt',
                    textColor: Colors.white,
                    onPressed: ref
                        .read(locationServiceProvider)
                        .openAppSettings,
                  )
                : null,
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
      body: AppPage(child: _buildBody(context, ref, state, notifier)),
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
          _StatusHeader(detail: detail),
          if (state.isPendingSync) ...[
            const SizedBox(height: 12),
            const _PendingSyncBanner(),
          ],
          const SizedBox(height: 12),
          _CustomerCard(
            customer: detail.pickup.customer,
            location: detail.pickup.location,
            onOpenInAppMap: detail.pickup.location.hasLocation
                ? () => context.push(
                    AppRoutes.pickupRouteLocation(detail.pickup.id),
                    extra: detail.pickup,
                  )
                : null,
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
          const SizedBox(height: 12),
          _ImagesCard(
            pickupId: detail.pickup.id,
            canManage: detail.pickup.status.value != 'pickup_da_lay',
          ),
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
    final busy = state.isSubmitting || state.isLocating;
    final label = state.isLocating
        ? 'Đang lấy vị trí...'
        : (state.isSubmitting ? 'Đang cập nhật...' : 'Cập nhật trạng thái');

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
            onPressed: busy
                ? null
                : () => _openStatusSheet(context, ref, transitions, notifier),
            icon: busy
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.sync_alt),
            label: Text(label),
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
    WidgetRef ref,
    List transitions,
    PickupDetailController notifier,
  ) async {
    final choice = await StatusActionSheet.show(
      context,
      transitions: transitions.cast(),
    );
    if (choice == null) return;
    final ok = await notifier.changeStatus(
      status: choice.status,
      reason: choice.reason,
    );
    // Đổi trạng thái xong → làm mới danh sách pickup để item nhảy đúng tab.
    if (ok) ref.invalidate(pickupListControllerProvider);
  }
}

class _StatusHeader extends StatelessWidget {
  const _StatusHeader({required this.detail});

  final PickupDetail detail;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final pickup = detail.pickup;
    return AppHeroPanel(
      trailingIcon: Icons.inventory_2_outlined,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
            ),
            child: const Icon(Icons.inventory_2_outlined, color: Colors.white),
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
                const SizedBox(height: 12),
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
                    if (pickup.scheduledAt != null)
                      _HeaderMetric(
                        icon: Icons.schedule_outlined,
                        label: DateFormatters.dateTime(pickup.scheduledAt),
                      ),
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

/// Banner báo có thao tác đổi trạng thái đang chờ đồng bộ (offline).
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
        border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
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

class _PendingSyncBanner extends StatelessWidget {
  const _PendingSyncBanner();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: theme.colorScheme.tertiaryContainer.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: theme.colorScheme.tertiary.withValues(alpha: 0.4),
        ),
      ),
      child: Row(
        children: [
          Icon(
            Icons.cloud_sync_outlined,
            size: 20,
            color: theme.colorScheme.tertiary,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Thao tác đang chờ đồng bộ. Sẽ tự gửi khi có mạng trở lại.',
              style: theme.textTheme.bodySmall,
            ),
          ),
        ],
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
  const _CustomerCard({
    required this.customer,
    required this.location,
    this.onOpenInAppMap,
  });

  final PickupCustomer customer;
  final PickupLocation location;

  /// Mở bản đồ chỉ đường trong app (null khi pickup chưa có toạ độ).
  final VoidCallback? onOpenInAppMap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final phone = customer.phone?.trim() ?? '';
    final address = customer.address?.trim() ?? '';

    return AppSurface(
      padding: EdgeInsets.zero,
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
                      child: FilledButton.icon(
                        onPressed: onOpenInAppMap,
                        icon: const Icon(Icons.directions_outlined, size: 18),
                        label: const Text('Chỉ đường'),
                      ),
                    ),
                ],
              ),
              if (location.hasLocation) ...[
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    onPressed: () => _openMap(context, customer),
                    icon: const Icon(Icons.open_in_new, size: 16),
                    label: const Text('Mở bằng Google Maps'),
                  ),
                ),
              ],
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
    return AppSurface(
      padding: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
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
            if (detail.checkin != null)
              _InfoRow(
                icon: Icons.my_location_outlined,
                label: 'Check-in GPS',
                value: detail.checkin!.at != null
                    ? DateFormatters.dateTime(detail.checkin!.at)
                    : 'Đã ghi nhận vị trí',
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
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 26,
            height: 26,
            decoration: BoxDecoration(
              color: theme.colorScheme.surfaceContainerLow,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              icon,
              size: 15,
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                      fontWeight: FontWeight.w700,
                      height: 1.1,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  value,
                  textAlign: TextAlign.right,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    height: 1.1,
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
    return AppSurface(
      padding: EdgeInsets.zero,
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
    return AppSurface(
      padding: EdgeInsets.zero,
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

/// Card ảnh bằng chứng pickup: lưới thumbnail + thêm/xóa ảnh.
class _ImagesCard extends ConsumerStatefulWidget {
  const _ImagesCard({required this.pickupId, required this.canManage});

  final int pickupId;
  final bool canManage;

  @override
  ConsumerState<_ImagesCard> createState() => _ImagesCardState();
}

class _ImagesCardState extends ConsumerState<_ImagesCard> {
  final _picker = ImagePicker();

  Future<void> _pickImage(ImageSource source) async {
    if (!widget.canManage) return;
    try {
      final file = await _picker.pickImage(
        source: source,
        maxWidth: 1600,
        imageQuality: 80,
      );
      if (file == null || !mounted) return;
      final ok = await ref
          .read(pickupImagesControllerProvider(widget.pickupId).notifier)
          .upload(file.path);
      if (!mounted) return;
      if (ok) {
        AppToast.success(context, 'Đã tải ảnh lên.');
      } else {
        final msg = ref
            .read(pickupImagesControllerProvider(widget.pickupId))
            .errorMessage;
        AppToast.error(context, msg ?? 'Không tải được ảnh.');
      }
    } catch (_) {
      if (mounted) {
        AppToast.error(context, 'Không mở được ảnh. Vui lòng thử lại.');
      }
    }
  }

  void _openPickerSheet() {
    if (!widget.canManage) return;
    showModalBottomSheet<void>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Chụp ảnh mới'),
              onTap: () {
                Navigator.pop(sheetContext);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Chọn từ thư viện'),
              onTap: () {
                Navigator.pop(sheetContext);
                _pickImage(ImageSource.gallery);
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmDelete(PickupImage image) async {
    if (!widget.canManage) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Xóa ảnh?'),
        content: const Text('Ảnh bằng chứng này sẽ bị xóa vĩnh viễn.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Xóa'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    final done = await ref
        .read(pickupImagesControllerProvider(widget.pickupId).notifier)
        .delete(image.id);
    if (mounted && done) AppToast.success(context, 'Đã xóa ảnh.');
  }

  void _openViewer(PickupImage image) {
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

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(pickupImagesControllerProvider(widget.pickupId));

    return AppSurface(
      padding: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: _CardTitle(
                    icon: Icons.photo_camera_back_outlined,
                    title: 'Ảnh bằng chứng (${state.images.length})',
                  ),
                ),
                if (widget.canManage && state.isUploading)
                  const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                else if (widget.canManage)
                  TextButton.icon(
                    onPressed: _openPickerSheet,
                    icon: const Icon(Icons.add_a_photo_outlined, size: 18),
                    label: const Text('Thêm'),
                  ),
              ],
            ),
            const SizedBox(height: 10),
            if (state.isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (state.images.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  'Chưa có ảnh. Chụp ảnh kiện hàng để làm bằng chứng.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              )
            else
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final image in state.images)
                    _ImageThumb(
                      image: image,
                      isDeleting: state.deletingId == image.id,
                      onTap: () => _openViewer(image),
                      onDelete: widget.canManage
                          ? () => _confirmDelete(image)
                          : null,
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

/// Thumbnail ảnh bằng chứng với nút xóa ở góc.
class _ImageThumb extends StatelessWidget {
  const _ImageThumb({
    required this.image,
    required this.isDeleting,
    required this.onTap,
    required this.onDelete,
  });

  final PickupImage image;
  final bool isDeleting;
  final VoidCallback onTap;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    const size = 92.0;

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        fit: StackFit.expand,
        children: [
          ClipRRect(
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
          if (onDelete != null)
            Positioned(
              top: 2,
              right: 2,
              child: Material(
                color: Colors.black.withValues(alpha: 0.55),
                shape: const CircleBorder(),
                child: InkWell(
                  customBorder: const CircleBorder(),
                  onTap: isDeleting ? null : onDelete,
                  child: Padding(
                    padding: const EdgeInsets.all(4),
                    child: isDeleting
                        ? const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(
                            Icons.close,
                            size: 14,
                            color: Colors.white,
                          ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
