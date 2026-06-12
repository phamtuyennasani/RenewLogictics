import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../shipper_pickup/domain/pickup.dart';
import 'ops_pickup_list_controller.dart';

/// Màn danh sách pickup của OPS.
class OpsPickupListScreen extends ConsumerStatefulWidget {
  const OpsPickupListScreen({super.key});

  @override
  ConsumerState<OpsPickupListScreen> createState() => _OpsPickupListScreenState();
}

class _OpsPickupListScreenState extends ConsumerState<OpsPickupListScreen> {
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
      ref.read(opsPickupListControllerProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(opsPickupListControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pickup của tôi'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(110),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(8.0),
                child: TextField(
                  controller: _searchController,
                  decoration: InputDecoration(
                    hintText: 'Tìm theo mã pickup...',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _searchController.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear),
                            onPressed: () {
                              _searchController.clear();
                              ref
                                  .read(opsPickupListControllerProvider.notifier)
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
                        .read(opsPickupListControllerProvider.notifier)
                        .setKeyword(value);
                  },
                ),
              ),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Row(
                  children: PickupTab.values.map((tab) {
                    final selected = state.tab == tab;
                    return Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(tab.label),
                        selected: selected,
                        onSelected: (sel) {
                          if (sel) {
                            ref
                                .read(opsPickupListControllerProvider.notifier)
                                .setTab(tab);
                          }
                        },
                      ),
                    );
                  }).toList(),
                ),
              ),
            ],
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () =>
            ref.read(opsPickupListControllerProvider.notifier).refresh(),
        child: state.isLoading && state.items.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : state.items.isEmpty
                ? const Center(child: Text('Chưa có pickup nào.'))
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
                      final pickup = state.items[index];
                      return _PickupCard(pickup: pickup);
                    },
                  ),
      ),
    );
  }
}

class _PickupCard extends StatelessWidget {
  const _PickupCard({required this.pickup});

  final Pickup pickup;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: InkWell(
        onTap: () {
          context.push('/ops/pickups/${pickup.id}');
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
                      pickup.maPickup,
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
                      pickup.status.label,
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
                'Khách: ${pickup.customer.displayName}',
                style: const TextStyle(fontSize: 14),
              ),
              if (pickup.customer.phone != null)
                Text(
                  'SĐT: ${pickup.customer.phone}',
                  style: const TextStyle(fontSize: 14, color: Colors.black54),
                ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Text(
                    '${pickup.packageCount ?? 0} kiện • ${pickup.weightKg?.toStringAsFixed(1) ?? "0.0"} kg',
                    style: const TextStyle(fontSize: 13, color: Colors.black54),
                  ),
                  const Spacer(),
                  if (pickup.scheduledAt != null)
                    Text(
                      'Hẹn: ${pickup.scheduledAt!.day}/${pickup.scheduledAt!.month}',
                      style: const TextStyle(fontSize: 12, color: Colors.orange),
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
