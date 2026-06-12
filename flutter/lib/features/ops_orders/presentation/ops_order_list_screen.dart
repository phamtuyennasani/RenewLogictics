import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../domain/ops_order.dart';
import 'ops_order_list_controller.dart';

/// Màn danh sách order của OPS.
class OpsOrderListScreen extends ConsumerStatefulWidget {
  const OpsOrderListScreen({super.key});

  @override
  ConsumerState<OpsOrderListScreen> createState() => _OpsOrderListScreenState();
}

class _OpsOrderListScreenState extends ConsumerState<OpsOrderListScreen> {
  final _scrollController = ScrollController();
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(opsOrderListControllerProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(opsOrderListControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Đơn hàng của tôi'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.all(8.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Tìm theo mã đơn, tracking...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          ref
                              .read(opsOrderListControllerProvider.notifier)
                              .setKeyword('');
                        },
                      )
                    : null,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                filled: true,
                fillColor: Colors.white,
              ),
              onSubmitted: (value) {
                ref
                    .read(opsOrderListControllerProvider.notifier)
                    .setKeyword(value);
              },
            ),
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(opsOrderListControllerProvider.notifier).refresh(),
        child: state.isLoading && state.items.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : state.items.isEmpty
                ? const Center(child: Text('Chưa có đơn hàng nào.'))
                : ListView.builder(
                    controller: _scrollController,
                    itemCount: state.items.length + (state.hasMore ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index == state.items.length) {
                        return const Center(
                          child: Padding(
                            padding: EdgeInsets.all(16.0),
                            child: CircularProgressIndicator(),
                          ),
                        );
                      }
                      final order = state.items[index];
                      return _OrderCard(order: order);
                    },
                  ),
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});

  final OpsOrder order;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: InkWell(
        onTap: () {
          context.push(AppRoutes.opsOrderDetailLocation(order.id));
        },
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      order.idBill,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.blue.shade100,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      order.status.label,
                      style: TextStyle(
                        color: Colors.blue.shade700,
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Gửi: ${order.sender.displayName}',
                style: const TextStyle(fontSize: 14),
              ),
              Text(
                'Nhận: ${order.receiver.fullname ?? "N/A"}',
                style: const TextStyle(fontSize: 14, color: Colors.black54),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Text(
                    '${order.packageCount ?? 0} kiện • ${order.weightKg?.toStringAsFixed(1) ?? "0.0"} kg',
                    style: const TextStyle(fontSize: 13, color: Colors.black54),
                  ),
                  const Spacer(),
                  if (order.hasPickup)
                    const Icon(Icons.check_circle,
                        size: 16, color: Colors.green),
                  if (order.hasPickup) const SizedBox(width: 4),
                  if (order.hasPickup)
                    const Text(
                      'Đã tạo pickup',
                      style: TextStyle(fontSize: 12, color: Colors.green),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
