import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/utils/date_formatters.dart';
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
              onScan: () => context.push(AppRoutes.shipperScan),
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
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Center(
                  child: _PickupEmptyState(
                    tab: state.tab,
                    hasKeyword: state.keyword.isNotEmpty,
                    onRefresh: () =>
                        ref.read(pickupListControllerProvider.notifier).refresh(),
                  ),
                ),
              ),
            );
          },
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
            key: ValueKey(pickup.id),
            pickup: pickup,
            onTap: () =>
                context.push(AppRoutes.pickupDetailLocation(pickup.id)),
          );
        },
      ),
    );
  }
}

class _PickupEmptyState extends StatelessWidget {
  const _PickupEmptyState({
    required this.tab,
    required this.hasKeyword,
    required this.onRefresh,
  });

  final PickupTab tab;
  final bool hasKeyword;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = hasKeyword ? 'Không tìm thấy pickup' : _titleFor(tab);
    final message = hasKeyword
        ? 'Thử đổi từ khóa hoặc xóa bộ lọc tìm kiếm để xem toàn bộ danh sách.'
        : _messageFor(tab);

    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            theme.colorScheme.surface,
            theme.colorScheme.primary.withValues(alpha: 0.06),
          ],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: theme.colorScheme.outlineVariant),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.05),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(22, 26, 22, 24),
        child: Column(
          children: [
            Container(
              width: 82,
              height: 82,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    theme.colorScheme.primary.withValues(alpha: 0.14),
                    const Color(0xFF06B6D4).withValues(alpha: 0.12),
                  ],
                ),
                borderRadius: BorderRadius.circular(26),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.9),
                  width: 1.4,
                ),
              ),
              child: Icon(
                _iconFor(tab, hasKeyword),
                size: 38,
                color: theme.colorScheme.primary,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              title,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w900,
                color: theme.colorScheme.onSurface,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                fontWeight: FontWeight.w600,
                height: 1.35,
              ),
            ),
            const SizedBox(height: 20),
            SizedBox(
              height: 44,
              child: OutlinedButton.icon(
                onPressed: onRefresh,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: const Text('Làm mới'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: theme.colorScheme.primary,
                  side: BorderSide(
                    color: theme.colorScheme.primary.withValues(alpha: 0.24),
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static String _titleFor(PickupTab tab) {
    switch (tab) {
      case PickupTab.newPickup:
        return 'Chưa có pickup mới';
      case PickupTab.accepted:
        return 'Chưa có pickup đã nhận';
      case PickupTab.picking:
        return 'Chưa có pickup đang lấy';
      case PickupTab.done:
        return 'Chưa có pickup hoàn tất';
    }
  }

  static String _messageFor(PickupTab tab) {
    switch (tab) {
      case PickupTab.newPickup:
        return 'Khi OPS giao phiếu mới, danh sách cần tiếp nhận sẽ xuất hiện tại đây.';
      case PickupTab.accepted:
        return 'Các phiếu bạn đã tiếp nhận nhưng chưa bắt đầu lấy hàng sẽ nằm ở mục này.';
      case PickupTab.picking:
        return 'Khi bắt đầu di chuyển lấy hàng, pickup đang xử lý sẽ được hiển thị tại đây.';
      case PickupTab.done:
        return 'Những pickup đã lấy hàng xong sẽ được lưu lại trong mục này.';
    }
  }

  static IconData _iconFor(PickupTab tab, bool hasKeyword) {
    if (hasKeyword) return Icons.manage_search_rounded;
    switch (tab) {
      case PickupTab.newPickup:
        return Icons.inbox_outlined;
      case PickupTab.accepted:
        return Icons.assignment_turned_in_outlined;
      case PickupTab.picking:
        return Icons.route_outlined;
      case PickupTab.done:
        return Icons.verified_outlined;
    }
  }
}

class _ListHeader extends StatelessWidget {
  const _ListHeader({
    required this.controller,
    required this.pendingCount,
    required this.onChanged,
    required this.onClear,
    required this.onProfile,
    required this.onScan,
    this.nearest,
  });

  final TextEditingController controller;
  final int pendingCount;
  final DateTime? nearest;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;
  final VoidCallback onProfile;
  final VoidCallback onScan;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      child: Column(
        children: [
          SizedBox(
            height: 42,
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    'Pickup của tôi',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: theme.colorScheme.onSurface,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: 'Quét nhận hàng',
                  onPressed: onScan,
                  icon: const Icon(Icons.qr_code_scanner_rounded),
                ),
                IconButton(
                  tooltip: 'Tài khoản',
                  onPressed: onProfile,
                  icon: const Icon(Icons.person_outline_rounded),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          _PendingSummaryCard(pendingCount: pendingCount, nearest: nearest),
          const SizedBox(height: 12),
          DecoratedBox(
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
            child: TextField(
              controller: controller,
              onChanged: onChanged,
              textInputAction: TextInputAction.search,
              style: TextStyle(
                color: theme.colorScheme.onSurface,
                fontWeight: FontWeight.w700,
              ),
              decoration: InputDecoration(
                hintText: 'Tìm mã pickup, tên, SĐT, địa chỉ',
                hintStyle: TextStyle(
                  color: theme.colorScheme.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
                prefixIcon: Icon(
                  Icons.search_rounded,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                suffixIcon: controller.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close_rounded),
                        onPressed: onClear,
                      ),
                filled: true,
                fillColor: Colors.transparent,
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 14,
                ),
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PendingSummaryCard extends StatelessWidget {
  const _PendingSummaryCard({
    required this.pendingCount,
    required this.nearest,
  });

  final int pendingCount;
  final DateTime? nearest;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final nearestText = nearest == null
        ? 'Chưa có'
        : DateFormatters.dateTime(nearest);

    return SizedBox(
      width: double.infinity,
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(22),
          boxShadow: [
            BoxShadow(
              color: theme.colorScheme.primary.withValues(alpha: 0.22),
              blurRadius: 28,
              offset: const Offset(0, 16),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(22),
          child: DecoratedBox(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Color(0xFF2563EB),
                  Color(0xFF1D4ED8),
                  Color(0xFF1E40AF),
                ],
              ),
            ),
            child: Stack(
              children: [
                Positioned(
                  right: 18,
                  top: 24,
                  child: Container(
                    width: 76,
                    height: 76,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.18),
                        width: 2,
                      ),
                    ),
                  ),
                ),
                Positioned(
                  right: 32,
                  top: 38,
                  child: Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white.withValues(alpha: 0.1),
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.42),
                        width: 3,
                      ),
                    ),
                    child: Icon(
                      Icons.schedule_rounded,
                      color: Colors.white.withValues(alpha: 0.86),
                      size: 26,
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 18, 92, 20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Đơn hàng chưa nhận',
                        style: theme.textTheme.titleMedium?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          height: 1.1,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '$pendingCount đơn hàng',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.displaySmall?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          height: 1,
                          letterSpacing: 0,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text.rich(
                        TextSpan(
                          text: 'Thời gian lấy hàng gần nhất: ',
                          children: [
                            TextSpan(
                              text: nearestText,
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ],
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: Colors.white.withValues(alpha: 0.84),
                          fontWeight: FontWeight.w700,
                          height: 1.25,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
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
