import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/detail_widgets.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../../shared/widgets/status_chip.dart';
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
    final notifier = ref.read(opsOrderListControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Đơn hàng của tôi'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(108),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
                child: _SearchField(
                  controller: _searchController,
                  hint: 'Tìm theo mã đơn, tracking...',
                  onSubmitted: notifier.setKeyword,
                  onClear: () => notifier.setKeyword(''),
                ),
              ),
              SizedBox(
                height: 44,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  children: [
                    _FilterChip(
                      label: 'Tất cả',
                      selected: state.hasPickupFilter == null,
                      onTap: () => notifier.setHasPickupFilter(null),
                    ),
                    const SizedBox(width: 8),
                    _FilterChip(
                      label: 'Chưa tạo pickup',
                      selected: state.hasPickupFilter == false,
                      onTap: () => notifier.setHasPickupFilter(false),
                    ),
                    const SizedBox(width: 8),
                    _FilterChip(
                      label: 'Đã tạo pickup',
                      selected: state.hasPickupFilter == true,
                      onTap: () => notifier.setHasPickupFilter(true),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: notifier.refresh,
        child: _buildBody(state, notifier),
      ),
    );
  }

  Widget _buildBody(OpsOrderListState state, OpsOrderListController notifier) {
    if (state.isLoading && state.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (state.errorMessage != null && state.items.isEmpty) {
      return ErrorState(message: state.errorMessage!, onRetry: notifier.refresh);
    }
    if (state.items.isEmpty) {
      return ListView(
        children: [
          SizedBox(height: MediaQuery.of(context).size.height * 0.18),
          EmptyState(
            icon: Icons.inventory_2_outlined,
            title: 'Chưa có đơn hàng',
            message: 'Không tìm thấy đơn hàng nào khớp bộ lọc hiện tại.',
            onRefresh: notifier.refresh,
          ),
        ],
      );
    }

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 24),
      itemCount: state.items.length + (state.hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (index == state.items.length) {
          return const Padding(
            padding: EdgeInsets.all(16),
            child: Center(child: CircularProgressIndicator()),
          );
        }
        return _OrderCard(order: state.items[index]);
      },
    );
  }
}

class _SearchField extends StatelessWidget {
  const _SearchField({
    required this.controller,
    required this.hint,
    required this.onSubmitted,
    required this.onClear,
  });

  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onSubmitted;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TextEditingValue>(
      valueListenable: controller,
      builder: (context, value, _) {
        return TextField(
          controller: controller,
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: const Icon(Icons.search),
            suffixIcon: value.text.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.clear),
                    onPressed: () {
                      controller.clear();
                      onClear();
                    },
                  )
                : null,
            isDense: true,
            filled: true,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: BorderSide.none,
            ),
          ),
          onSubmitted: onSubmitted,
        );
      },
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});

  final OpsOrder order;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final receiver = order.receiver.fullname?.trim() ?? '';

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push(AppRoutes.opsOrderDetailLocation(order.id)),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      order.idBill,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 10),
                  StatusChip(badge: order.status),
                ],
              ),
              if (order.trackingCode != null &&
                  order.trackingCode!.trim().isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  order.trackingCode!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
              const SizedBox(height: 12),
              _PartyRow(
                icon: Icons.north_east,
                label: 'Gửi',
                name: order.sender.displayName,
              ),
              if (receiver.isNotEmpty) ...[
                const SizedBox(height: 6),
                _PartyRow(
                  icon: Icons.south_west,
                  label: 'Nhận',
                  name: receiver,
                ),
              ],
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  MetaChip(
                    icon: Icons.inventory_2_outlined,
                    label: '${order.packageCount ?? 0} kiện',
                  ),
                  if (order.weightKg != null)
                    MetaChip(
                      icon: Icons.scale_outlined,
                      label: DateFormatters.weight(order.weightKg),
                    ),
                  if (order.createdAt != null)
                    MetaChip(
                      icon: Icons.event_outlined,
                      label: DateFormatters.date(order.createdAt),
                    ),
                  if (order.hasPickup)
                    MetaChip(
                      icon: Icons.check_circle_outline,
                      label: 'Đã tạo pickup',
                      color: Colors.green,
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

class _PartyRow extends StatelessWidget {
  const _PartyRow({
    required this.icon,
    required this.label,
    required this.name,
  });

  final IconData icon;
  final String label;
  final String name;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        Icon(icon, size: 16, color: theme.colorScheme.onSurfaceVariant),
        const SizedBox(width: 6),
        Text(
          '$label: ',
          style: theme.textTheme.bodyMedium?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        Expanded(
          child: Text(
            name,
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
