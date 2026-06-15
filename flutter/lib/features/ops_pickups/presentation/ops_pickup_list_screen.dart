import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/utils/date_formatters.dart';
import '../../../shared/widgets/empty_state.dart';
import '../../../shared/widgets/error_state.dart';
import '../../shipper_pickup/presentation/widgets/pickup_card.dart';
import '../data/ops_pickup_repository.dart';
import 'ops_pickup_list_controller.dart';

/// Màn danh sách pickup của OPS.
class OpsPickupListScreen extends ConsumerStatefulWidget {
  const OpsPickupListScreen({super.key});

  @override
  ConsumerState<OpsPickupListScreen> createState() =>
      _OpsPickupListScreenState();
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
    final notifier = ref.read(opsPickupListControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pickup của tôi'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(108),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
                child: ValueListenableBuilder<TextEditingValue>(
                  valueListenable: _searchController,
                  builder: (context, value, _) {
                    return TextField(
                      controller: _searchController,
                      textInputAction: TextInputAction.search,
                      decoration: InputDecoration(
                        hintText: 'Tìm theo mã pickup, khách...',
                        prefixIcon: const Icon(Icons.search),
                        suffixIcon: value.text.isNotEmpty
                            ? IconButton(
                                icon: const Icon(Icons.clear),
                                onPressed: () {
                                  _searchController.clear();
                                  notifier.setKeyword('');
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
                      onSubmitted: notifier.setKeyword,
                    );
                  },
                ),
              ),
              SizedBox(
                height: 44,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  children: [
                    for (final tab in PickupTab.values) ...[
                      ChoiceChip(
                        label: Text(tab.label),
                        selected: state.tab == tab,
                        onSelected: (sel) {
                          if (sel) notifier.setTab(tab);
                        },
                      ),
                      const SizedBox(width: 8),
                    ],
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

  Widget _buildBody(OpsPickupListState state, OpsPickupListController notifier) {
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
            icon: Icons.local_shipping_outlined,
            title: 'Chưa có pickup',
            message: 'Không có phiếu lấy hàng nào trong mục "${state.tab.label}".',
            onRefresh: notifier.refresh,
          ),
        ],
      );
    }

    final summary = state.summary;
    final showSummary =
        summary.pendingCount > 0 || summary.nearestScheduleAt != null;

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 24),
      itemCount: state.items.length +
          (showSummary ? 1 : 0) +
          (state.hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        var i = index;
        if (showSummary) {
          if (i == 0) return _SummaryBanner(summary: summary);
          i -= 1;
        }
        if (i == state.items.length) {
          return const Padding(
            padding: EdgeInsets.all(16),
            child: Center(child: CircularProgressIndicator()),
          );
        }
        final pickup = state.items[i];
        return PickupCard(
          pickup: pickup,
          onTap: () => context.push('/ops/pickups/${pickup.id}'),
        );
      },
    );
  }
}

class _SummaryBanner extends StatelessWidget {
  const _SummaryBanner({required this.summary});

  final PickupSummary summary;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final pending = summary.pendingCount;
    final nearest = summary.nearestScheduleAt;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: theme.colorScheme.primary.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: theme.colorScheme.primary.withValues(alpha: 0.2),
        ),
      ),
      child: Row(
        children: [
          Icon(Icons.pending_actions_outlined,
              color: theme.colorScheme.primary),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$pending phiếu chờ xử lý',
                  style: theme.textTheme.bodyLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (nearest != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    'Hẹn gần nhất: ${DateFormatters.dateTime(nearest)}',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
