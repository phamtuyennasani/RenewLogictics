import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/pickup.dart';
import '../domain/pickup_repository.dart';
import 'pickup_providers.dart';

/// State của màn danh sách pickup theo từng tab.
class PickupListState {
  const PickupListState({
    this.tab = PickupTab.newPickup,
    this.keyword = '',
    this.items = const [],
    this.summary = const PickupSummary(),
    this.isLoading = false,
    this.isLoadingMore = false,
    this.errorMessage,
    this.currentPage = 1,
    this.hasMore = false,
  });

  final PickupTab tab;
  final String keyword;
  final List<Pickup> items;
  final PickupSummary summary;
  final bool isLoading;
  final bool isLoadingMore;
  final String? errorMessage;
  final int currentPage;
  final bool hasMore;

  PickupListState copyWith({
    PickupTab? tab,
    String? keyword,
    List<Pickup>? items,
    PickupSummary? summary,
    bool? isLoading,
    bool? isLoadingMore,
    String? errorMessage,
    bool clearError = false,
    int? currentPage,
    bool? hasMore,
  }) {
    return PickupListState(
      tab: tab ?? this.tab,
      keyword: keyword ?? this.keyword,
      items: items ?? this.items,
      summary: summary ?? this.summary,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      currentPage: currentPage ?? this.currentPage,
      hasMore: hasMore ?? this.hasMore,
    );
  }
}

/// Controller danh sách pickup: đổi tab, tìm kiếm, refresh, load more.
class PickupListController extends Notifier<PickupListState> {
  PickupRepository get _repo => ref.read(pickupRepositoryProvider);

  @override
  PickupListState build() {
    // Tải tab mặc định khi khởi tạo.
    Future.microtask(refresh);
    return const PickupListState(isLoading: true);
  }

  Future<void> setTab(PickupTab tab) async {
    if (tab == state.tab) return;
    state = state.copyWith(tab: tab);
    await refresh();
  }

  Future<void> setKeyword(String keyword) async {
    state = state.copyWith(keyword: keyword);
    await refresh();
  }

  /// Tải lại từ trang 1 (pull-to-refresh / đổi tab / tìm kiếm).
  Future<void> refresh() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _repo.list(
        tab: state.tab,
        keyword: state.keyword.isEmpty ? null : state.keyword,
        page: 1,
      );
      state = state.copyWith(
        items: result.page.items,
        summary: result.summary,
        currentPage: result.page.currentPage,
        hasMore: result.page.hasMore,
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
        tab: state.tab,
        keyword: state.keyword.isEmpty ? null : state.keyword,
        page: state.currentPage + 1,
      );
      state = state.copyWith(
        items: [...state.items, ...result.page.items],
        currentPage: result.page.currentPage,
        hasMore: result.page.hasMore,
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

final pickupListControllerProvider =
    NotifierProvider<PickupListController, PickupListState>(
  PickupListController.new,
);
