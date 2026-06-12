import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shipper_pickup/domain/pickup.dart';
import '../data/ops_pickup_repository.dart';
import 'ops_pickup_providers.dart';

enum PickupTab { newPickup, accepted, picking, done }

extension PickupTabExt on PickupTab {
  String get value {
    switch (this) {
      case PickupTab.newPickup:
        return 'new';
      case PickupTab.accepted:
        return 'accepted';
      case PickupTab.picking:
        return 'picking';
      case PickupTab.done:
        return 'done';
    }
  }

  String get label {
    switch (this) {
      case PickupTab.newPickup:
        return 'Mới';
      case PickupTab.accepted:
        return 'Đã nhận';
      case PickupTab.picking:
        return 'Đang lấy';
      case PickupTab.done:
        return 'Hoàn tất';
    }
  }
}

/// State của màn danh sách pickup OPS.
class OpsPickupListState {
  const OpsPickupListState({
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

  OpsPickupListState copyWith({
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
    return OpsPickupListState(
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

/// Controller danh sách pickup OPS.
class OpsPickupListController extends Notifier<OpsPickupListState> {
  OpsPickupRepository get _repo => ref.read(opsPickupRepositoryProvider);

  @override
  OpsPickupListState build() {
    Future.microtask(refresh);
    return const OpsPickupListState(isLoading: true);
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

  Future<void> refresh() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final result = await _repo.list(
        tab: state.tab.value,
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

  Future<void> loadMore() async {
    if (state.isLoadingMore || !state.hasMore || state.isLoading) return;
    state = state.copyWith(isLoadingMore: true);
    try {
      final result = await _repo.list(
        tab: state.tab.value,
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

final opsPickupListControllerProvider =
    NotifierProvider<OpsPickupListController, OpsPickupListState>(
  OpsPickupListController.new,
);
