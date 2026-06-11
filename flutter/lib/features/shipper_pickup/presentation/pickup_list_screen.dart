import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../domain/pickup_repository.dart';
import 'pickup_list_controller.dart';
import 'widgets/pickup_card.dart';

/// Danh sách pickup của shipper (contract §3.1).
/// TabBar theo §6.1, tìm kiếm, pull-to-refresh, infinite scroll.
class PickupListScreen extends ConsumerStatefulWidget {
  const PickupListScreen({super.key});

  @override
  ConsumerState<PickupListScreen> createState() => _PickupListScreenState();
}

class _PickupListScreenState extends ConsumerState<PickupListScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final _scrollController = ScrollController();
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  static const _tabs = PickupTab.values;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
    _tabController.addListener(_onTabChanged);
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _tabController.removeListener(_onTabChanged);
    _tabController.dispose();
    _scrollController.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  void _onTabChanged() {
    if (_tabController.indexIsChanging) return;
    ref.read(pickupListControllerProvider.notifier).setTab(_tabs[_tabController.index]);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 240) {
      ref.read(pickupListControllerProvider.notifier).loadMore();
    }
  }

  void _onSearchChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      ref.read(pickupListControllerProvider.notifier).setKeyword(value.trim());
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(pickupListControllerProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pickup của tôi'),
        actions: [
          IconButton(
            icon: const Icon(Icons.person_outline),
            tooltip: 'Tài khoản',
            onPressed: () => context.push('/profile'),
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(104),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                child: TextField(
                  controller: _searchCtrl,
                  onChanged: _onSearchChanged,
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    hintText: 'Tìm mã pickup, tên, SĐT, địa chỉ',
                    prefixIcon: const Icon(Icons.search),
                    isDense: true,
                    suffixIcon: _searchCtrl.text.isEmpty
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.clear),
                            onPressed: () {
                              _searchCtrl.clear();
                              _onSearchChanged('');
                              FocusScope.of(context).unfocus();
                            },
                          ),
                  ),
                ),
              ),
              TabBar(
                controller: _tabController,
                isScrollable: true,
                tabAlignment: TabAlignment.start,
                tabs: [for (final t in _tabs) Tab(text: t.label)],
              ),
            ],
          ),
        ),
      ),
      body: Column(
        children: [
          if (state.summary.pendingCount > 0 ||
              state.summary.nearestScheduleAt != null)
            _SummaryBar(
              pendingCount: state.summary.pendingCount,
              nearest: state.summary.nearestScheduleAt,
            ),
          Expanded(child: _buildBody(state, theme)),
        ],
      ),
    );
  }

  Widget _buildBody(PickupListState state, ThemeData theme) {
    if (state.isLoading && state.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (state.errorMessage != null && state.items.isEmpty) {
      return ErrorState(
        message: state.errorMessage!,
        onRetry: () =>
            ref.read(pickupListControllerProvider.notifier).refresh(),
      );
    }
    if (state.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: () =>
            ref.read(pickupListControllerProvider.notifier).refresh(),
        child: ListView(
          children: const [
            SizedBox(height: 120),
            EmptyState(
              icon: Icons.inbox_outlined,
              title: 'Chưa có pickup',
              message: 'Không có pickup nào trong mục này.',
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () =>
          ref.read(pickupListControllerProvider.notifier).refresh(),
      child: ListView.builder(
        controller: _scrollController,
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: state.items.length + (state.hasMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index >= state.items.length) {
            return const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          final pickup = state.items[index];
          return PickupCard(
            pickup: pickup,
            onTap: () => context.push('/shipper/pickups/${pickup.id}'),
          );
        },
      ),
    );
  }
}

class _SummaryBar extends StatelessWidget {
  const _SummaryBar({required this.pendingCount, this.nearest});

  final int pendingCount;
  final DateTime? nearest;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      color: theme.colorScheme.primaryContainer.withValues(alpha: 0.4),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Icon(Icons.local_shipping_outlined,
              size: 18, color: theme.colorScheme.primary),
          const SizedBox(width: 8),
          Text('$pendingCount pickup chờ lấy',
              style: theme.textTheme.bodyMedium
                  ?.copyWith(fontWeight: FontWeight.w600)),
          if (nearest != null) ...[
            const Spacer(),
            Icon(Icons.schedule, size: 16, color: theme.hintColor),
            const SizedBox(width: 4),
            Text('Gần nhất ${DateFormatters.dateTime(nearest)}',
                style: theme.textTheme.bodySmall),
          ],
        ],
      ),
    );
  }
}
