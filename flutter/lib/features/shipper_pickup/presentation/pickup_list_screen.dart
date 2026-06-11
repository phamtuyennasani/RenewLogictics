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
    ref
        .read(pickupListControllerProvider.notifier)
        .setTab(_tabs[_tabController.index]);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 240) {
      ref.read(pickupListControllerProvider.notifier).loadMore();
    }
  }

  void _onSearchChanged(String value) {
    setState(() {});
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
      body: SafeArea(
        child: Column(
          children: [
            _ListHeader(
              controller: _searchCtrl,
              pendingCount: state.summary.pendingCount,
              nearest: state.summary.nearestScheduleAt,
              onChanged: _onSearchChanged,
              onClear: () {
                _searchCtrl.clear();
                _onSearchChanged('');
                FocusScope.of(context).unfocus();
              },
              onProfile: () => context.push('/profile'),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
              child: _PickupSegmentedTabs(
                controller: _tabController,
                tabs: _tabs,
              ),
            ),
            Expanded(child: _buildBody(state, theme)),
          ],
        ),
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
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
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

class _ListHeader extends StatelessWidget {
  const _ListHeader({
    required this.controller,
    required this.pendingCount,
    required this.onChanged,
    required this.onClear,
    required this.onProfile,
    this.nearest,
  });

  final TextEditingController controller;
  final int pendingCount;
  final DateTime? nearest;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;
  final VoidCallback onProfile;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              const Color(0xFF0F766E),
              const Color(0xFF155E75),
              theme.colorScheme.secondary,
            ],
          ),
          borderRadius: BorderRadius.circular(24),
          boxShadow: [
            BoxShadow(
              color: theme.colorScheme.primary.withValues(alpha: 0.2),
              blurRadius: 28,
              offset: const Offset(0, 16),
            ),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Pickup của tôi',
                          style: theme.textTheme.headlineSmall?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Theo dõi và xử lý phiếu lấy hàng trong ngày',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: Colors.white.withValues(alpha: 0.74),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  IconButton.filledTonal(
                    tooltip: 'Tài khoản',
                    onPressed: onProfile,
                    style: IconButton.styleFrom(
                      backgroundColor: Colors.white.withValues(alpha: 0.16),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    icon: const Icon(Icons.person_outline),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              DecoratedBox(
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.22),
                  ),
                ),
                child: TextField(
                  controller: controller,
                  onChanged: onChanged,
                  textInputAction: TextInputAction.search,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                  cursorColor: Colors.white,
                  decoration: InputDecoration(
                    hintText: 'Tìm mã pickup, tên, SĐT, địa chỉ',
                    hintStyle: TextStyle(
                      color: Colors.white.withValues(alpha: 0.66),
                      fontWeight: FontWeight.w600,
                    ),
                    prefixIcon: Icon(
                      Icons.search_rounded,
                      color: Colors.white.withValues(alpha: 0.78),
                    ),
                    suffixIcon: controller.text.isEmpty
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.close_rounded),
                            color: Colors.white,
                            onPressed: onClear,
                          ),
                    filled: true,
                    fillColor: Colors.transparent,
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                  ),
                ),
              ),
              const SizedBox(height: 14),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    _SummaryPill(
                      icon: Icons.local_shipping_outlined,
                      text: '$pendingCount chờ lấy',
                      color: Colors.white,
                      inverted: true,
                    ),
                    if (nearest != null) ...[
                      const SizedBox(width: 8),
                      _SummaryPill(
                        icon: Icons.schedule,
                        text: 'Gần nhất ${DateFormatters.dateTime(nearest)}',
                        color: Colors.white,
                        inverted: true,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PickupSegmentedTabs extends StatelessWidget {
  const _PickupSegmentedTabs({required this.controller, required this.tabs});

  final TabController controller;
  final List<PickupTab> tabs;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: theme.colorScheme.outlineVariant),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(5),
        child: AnimatedBuilder(
          animation: controller,
          builder: (context, _) {
            return Row(
              children: [
                for (var i = 0; i < tabs.length; i++)
                  Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(
                        left: i == 0 ? 0 : 3,
                        right: i == tabs.length - 1 ? 0 : 3,
                      ),
                      child: _PickupSegment(
                        tab: tabs[i],
                        selected: controller.index == i,
                        onTap: () => controller.animateTo(i),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _PickupSegment extends StatelessWidget {
  const _PickupSegment({
    required this.tab,
    required this.selected,
    required this.onTap,
  });

  final PickupTab tab;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = selected
        ? theme.colorScheme.primary
        : theme.colorScheme.onSurfaceVariant;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          curve: Curves.easeOutCubic,
          height: 40,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected
                ? theme.colorScheme.primary.withValues(alpha: 0.1)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(14),
          ),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(_iconFor(tab), size: 16, color: color),
                const SizedBox(width: 5),
                Text(
                  tab.label,
                  maxLines: 1,
                  style: TextStyle(
                    color: color,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    height: 1,
                    letterSpacing: 0,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  IconData _iconFor(PickupTab tab) {
    switch (tab) {
      case PickupTab.newPickup:
        return Icons.auto_awesome_outlined;
      case PickupTab.accepted:
        return Icons.assignment_turned_in_outlined;
      case PickupTab.picking:
        return Icons.route_outlined;
      case PickupTab.done:
        return Icons.verified_outlined;
    }
  }
}

class _SummaryPill extends StatelessWidget {
  const _SummaryPill({
    required this.icon,
    required this.text,
    required this.color,
    this.inverted = false,
  });

  final IconData icon;
  final String text;
  final Color color;
  final bool inverted;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      constraints: const BoxConstraints(minHeight: 34),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: inverted
            ? Colors.white.withValues(alpha: 0.14)
            : color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: inverted
              ? Colors.white.withValues(alpha: 0.2)
              : color.withValues(alpha: 0.14),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              text,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                color: color,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
