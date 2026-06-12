import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shipper_pickup/domain/pickup.dart';
import '../data/ops_pickup_repository.dart';
import 'ops_pickup_list_controller.dart';
import 'ops_pickup_providers.dart';

/// Controller chi tiết pickup OPS.
class OpsPickupDetailController extends StateNotifier<AsyncValue<PickupDetail>> {
  OpsPickupDetailController(this._repo, this.pickupId) : super(const AsyncLoading()) {
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

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chi tiết Pickup'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              ref.read(opsPickupDetailControllerProvider(pickupId).notifier).refresh();
            },
          ),
        ],
      ),
      body: state.when(
        data: (detail) => _DetailContent(detail: detail, pickupId: pickupId),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text('Lỗi: ${err.toString()}'),
              ElevatedButton(
                onPressed: () {
                  ref.read(opsPickupDetailControllerProvider(pickupId).notifier).refresh();
                },
                child: const Text('Thử lại'),
              ),
            ],
          ),
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
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Section(
            title: 'Thông tin pickup',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _InfoRow(label: 'Mã pickup', value: pickup.maPickup),
                _InfoRow(label: 'Trạng thái', value: pickup.status.label),
                if (pickup.createdBy != null)
                  _InfoRow(label: 'Người tạo', value: pickup.createdBy!),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _Section(
            title: 'Khách hàng',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (pickup.customer.company != null)
                  _InfoRow(label: 'Công ty', value: pickup.customer.company!),
                if (pickup.customer.fullname != null)
                  _InfoRow(label: 'Họ tên', value: pickup.customer.fullname!),
                if (pickup.customer.phone != null)
                  _InfoRow(label: 'Điện thoại', value: pickup.customer.phone!),
                if (pickup.customer.address != null)
                  _InfoRow(label: 'Địa chỉ', value: pickup.customer.address!),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _Section(
            title: 'Đơn hàng (${detail.orders.length})',
            child: Column(
              children: detail.orders.map((order) {
                return Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(
                    children: [
                      Expanded(child: Text(order.idBill ?? 'N/A')),
                      if (order.trackingCode != null)
                        Text(order.trackingCode!,
                            style: const TextStyle(color: Colors.black54)),
                    ],
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 24),
          _Section(
            title: 'Shipper',
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    pickup.createdBy ?? 'Chưa gán',
                    style: const TextStyle(fontWeight: FontWeight.w500),
                  ),
                ),
                ElevatedButton.icon(
                  onPressed: () {
                    _showShipperSheet(context, ref);
                  },
                  icon: const Icon(Icons.person_add, size: 18),
                  label: const Text('Chọn shipper'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _showShipperSheet(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      builder: (ctx) => _ShipperPickerSheet(
        pickupId: pickupId,
        onSelected: (shipperId) {
          Navigator.of(ctx).pop();
          _assignShipper(context, ref, shipperId);
        },
      ),
    );
  }

  Future<void> _assignShipper(BuildContext context, WidgetRef ref, int shipperId) async {
    try {
      await ref.read(opsPickupDetailControllerProvider(pickupId).notifier).assignShipper(shipperId);
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã gán shipper thành công')),
      );
      // Refresh pickup list
      ref.read(opsPickupListControllerProvider.notifier).refresh();
    } catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Lỗi: ${e.toString()}')),
      );
    }
  }
}

class _ShipperPickerSheet extends ConsumerStatefulWidget {
  const _ShipperPickerSheet({required this.pickupId, required this.onSelected});

  final int pickupId;
  final void Function(int shipperId) onSelected;

  @override
  ConsumerState<_ShipperPickerSheet> createState() => _ShipperPickerSheetState();
}

class _ShipperPickerSheetState extends ConsumerState<_ShipperPickerSheet> {
  List<ShipperOption> _shippers = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadShippers();
  }

  Future<void> _loadShippers() async {
    setState(() => _isLoading = true);
    try {
      final shippers = await ref.read(opsPickupRepositoryProvider).shippers();
      if (mounted) setState(() {
        _shippers = shippers;
        _isLoading = false;
      });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'Chọn shipper',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          if (_isLoading)
            const CircularProgressIndicator()
          else
            ..._shippers.map((shipper) {
              return ListTile(
                title: Text(shipper.name),
                onTap: () => widget.onSelected(shipper.id),
              );
            }),
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 8),
        child,
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              label,
              style: const TextStyle(color: Colors.black54),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}
