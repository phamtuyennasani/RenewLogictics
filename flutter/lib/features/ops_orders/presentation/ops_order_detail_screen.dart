import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

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

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chi tiết đơn hàng'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              ref.read(opsOrderDetailControllerProvider(orderId).notifier).refresh();
            },
          ),
        ],
      ),
      body: state.when(
        data: (detail) => _DetailContent(detail: detail),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (err, _) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text('Lỗi: ${err.toString()}'),
              ElevatedButton(
                onPressed: () {
                  ref.read(opsOrderDetailControllerProvider(orderId).notifier).refresh();
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

class _DetailContent extends StatelessWidget {
  const _DetailContent({required this.detail});

  final OpsOrderDetail detail;

  @override
  Widget build(BuildContext context) {
    final order = detail.order;
    final orderId = order.id;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Section(
            title: 'Thông tin đơn',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _InfoRow(label: 'Mã đơn', value: order.idBill),
                if (order.trackingCode != null)
                  _InfoRow(label: 'Tracking', value: order.trackingCode!),
                _InfoRow(label: 'Trạng thái', value: order.status.label),
                if (order.saleName != null)
                  _InfoRow(label: 'Sale', value: order.saleName!),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _Section(
            title: 'Người gửi',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (order.sender.company != null)
                  _InfoRow(label: 'Công ty', value: order.sender.company!),
                if (order.sender.fullname != null)
                  _InfoRow(label: 'Họ tên', value: order.sender.fullname!),
                if (order.sender.phone != null)
                  _InfoRow(label: 'Điện thoại', value: order.sender.phone!),
                if (order.sender.address != null)
                  _InfoRow(label: 'Địa chỉ', value: order.sender.address!),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _Section(
            title: 'Kiện hàng (${detail.packages.length})',
            child: Column(
              children: detail.packages.map((pkg) {
                return Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(
                    children: [
                      Text('Kiện ${pkg.numberOfPackage}'),
                      const Spacer(),
                      Text('${pkg.cWeight.toStringAsFixed(1)} kg'),
                    ],
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 24),
          if (detail.canCreatePickup)
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  context.push('/ops/orders/$orderId/create-pickup', extra: detail);
                },
                icon: const Icon(Icons.add),
                label: const Text('Tạo phiếu pickup'),
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.all(16),
                ),
              ),
            )
          else
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.green.shade50,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Row(
                children: [
                  Icon(Icons.check_circle, color: Colors.green),
                  SizedBox(width: 8),
                  Text('Đơn hàng đã có phiếu pickup'),
                ],
              ),
            ),
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
