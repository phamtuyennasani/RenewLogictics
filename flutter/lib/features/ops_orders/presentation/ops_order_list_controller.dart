import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/ops_order.dart';
import '../domain/ops_order_repository.dart';
import 'ops_order_providers.dart';

/// State của màn danh sách order OPS.
class OpsOrderListState {
  const OpsOrderListState({
    this.keyword = '',
    this.statusFilter,
    this.hasPickupFilter,
    this.items = const [],
    this.isLoading = false,
    this.isLoadingMore = false,
    this.errorMessage,
    this.currentPage = 1,
    this.hasMore = false,
  });

  final String keyword;
  final String? statusFilter;
  final bool? hasPickupFilter;
  final List<OpsOrder> items;
  final bool isLoading;
  final bool isLoadingMore;
  final String? errorMessage;
  final int currentPage;
  final bool hasMore;

  OpsOrderListState copyWith({
    String? keyword,
    String? statusFilter,
    bool clearStatusFilter = false,
    bool? hasPickupFilter,
    bool clearHasPickupFilter = false,
    List<OpsOrder>? items,
    bool? isLoading,
    bool? isLoadingMore,
    String? errorMessage,
    bool clearError = false,
    int? currentPage,
    bool? hasMore,
  }) {
    return OpsOrderListState(
      keyword: keyword ?? this.keyword,
      statusFilter: clearStatusFilter
          ? null
          : (statusFilter ?? this.statusFilter),
      hasPickupFilter: clearHasPickupFilter
          ? null
          : (hasPickupFilter ?? this.hasPickupFilter),
      items: items ?? this.items,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      currentPage: currentPage ?? this.currentPage,
      hasMore: hasMore ?? this.hasMore,
    );
  }
}

/// Controller danh sách order OPS: filter, search, refresh, load more.
class OpsOrderListController extends Notifier<OpsOrderListState> {
  OpsOrderRepository get _repo => ref.read(opsOrderRepositoryProvider);

  @override
  OpsOrderListState build() {
    // Tải trang đầu khi khởi tạo.
    Future.microtask(refresh);
    return const OpsOrderListState(isLoading: true);
  }

  Future<void> setKeyword(String keyword) async {
    state = state.copyWith(keyword: keyword);
    await refresh();
  }

  Future<void> setStatusFilter(String? status) async {
    state = state.copyWith(
      statusFilter: status,
      clearStatusFilter: status == null,
    );
    await refresh();
  }

  Future<void> setHasPickupFilter(bool? hasPickup) async {
    state = state.copyWith(
      hasPickupFilter: hasPickup,
      clearHasPickupFilter: hasPickup == null,
    );
    await refresh();
  }

  /// Tải lại từ trang 1 (pull-to-refresh / đổi filter).
  Future<void> refresh() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _repo.list(
        keyword: state.keyword.isEmpty ? null : state.keyword,
        status: state.statusFilter,
        hasPickup: state.hasPickupFilter,
        page: 1,
      );
      state = state.copyWith(
        items: result.items,
        currentPage: result.currentPage,
        hasMore: result.hasMore,
        isLoading: false,
      );
    } on Object catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: _messageOf(e));
    }
  }

  /// Tải trang kế (infinite scroll).
  Future<void> loadMore() async {
    if (state.isLoadingMore || !state.hasMore || state.isLoading) return;
    state = state.copyWith(isLoadingMore: true);
    try {
      final result = await _repo.list(
        keyword: state.keyword.isEmpty ? null : state.keyword,
        status: state.statusFilter,
        hasPickup: state.hasPickupFilter,
        page: state.currentPage + 1,
      );
      state = state.copyWith(
        items: [...state.items, ...result.items],
        currentPage: result.currentPage,
        hasMore: result.hasMore,
        isLoadingMore: false,
      );
    } on Object catch (e) {
      state = state.copyWith(isLoadingMore: false, errorMessage: _messageOf(e));
    }
  }

  static String _messageOf(Object e) {
    try {
      final dynamic dyn = e;
      final m = dyn.message;
      if (m is String && m.isNotEmpty) return m;
    } catch (_) {}
    return 'Không tải được danh sách. Vui lòng thử lại.';
  }
}

final opsOrderListControllerProvider =
    NotifierProvider<OpsOrderListController, OpsOrderListState>(
      OpsOrderListController.new,
    );
