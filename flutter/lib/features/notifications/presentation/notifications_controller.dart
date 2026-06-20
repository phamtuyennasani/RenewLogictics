import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/app_notification.dart';
import 'notifications_providers.dart';

class NotificationsState {
  const NotificationsState({
    this.items = const [],
    this.isLoading = false,
    this.isLoadingMore = false,
    this.errorMessage,
    this.page = 1,
    this.hasMore = false,
    this.unreadCount = 0,
  });

  final List<AppNotification> items;
  final bool isLoading;
  final bool isLoadingMore;
  final String? errorMessage;
  final int page;
  final bool hasMore;
  final int unreadCount;

  NotificationsState copyWith({
    List<AppNotification>? items,
    bool? isLoading,
    bool? isLoadingMore,
    String? errorMessage,
    bool clearError = false,
    int? page,
    bool? hasMore,
    int? unreadCount,
  }) {
    return NotificationsState(
      items: items ?? this.items,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      page: page ?? this.page,
      hasMore: hasMore ?? this.hasMore,
      unreadCount: unreadCount ?? this.unreadCount,
    );
  }
}

class NotificationsController extends StateNotifier<NotificationsState> {
  NotificationsController(this._ref) : super(const NotificationsState()) {
    load();
  }

  final Ref _ref;

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true, page: 1);
    try {
      final page = await _ref
          .read(notificationsRepositoryProvider)
          .list(page: 1);
      state = state.copyWith(
        items: page.items,
        isLoading: false,
        page: page.currentPage,
        hasMore: page.hasMore,
        unreadCount: page.unreadCount ?? state.unreadCount,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: _messageOf(e));
    }
  }

  Future<void> loadMore() async {
    if (state.isLoadingMore || !state.hasMore) return;
    state = state.copyWith(isLoadingMore: true, clearError: true);
    try {
      final next = await _ref
          .read(notificationsRepositoryProvider)
          .list(page: state.page + 1);
      state = state.copyWith(
        items: [...state.items, ...next.items],
        isLoadingMore: false,
        page: next.currentPage,
        hasMore: next.hasMore,
      );
    } catch (e) {
      state = state.copyWith(isLoadingMore: false, errorMessage: _messageOf(e));
    }
  }

  Future<void> markRead(AppNotification item) async {
    if (item.isRead) return;
    state = state.copyWith(
      items: [
        for (final current in state.items)
          current.id == item.id ? current.copyWith(isRead: true) : current,
      ],
      unreadCount: state.unreadCount > 0 ? state.unreadCount - 1 : 0,
    );
    try {
      await _ref.read(notificationsRepositoryProvider).markRead(item.id);
    } catch (_) {}
  }

  /// Đánh dấu tất cả thông báo đang hiển thị là đã đọc (optimistic).
  Future<void> markAllRead() async {
    if (state.unreadCount == 0 && state.items.every((i) => i.isRead)) {
      return;
    }
    final previous = state;
    state = state.copyWith(
      items: [
        for (final current in state.items)
          current.isRead ? current : current.copyWith(isRead: true),
      ],
      unreadCount: 0,
    );
    try {
      await _ref.read(notificationsRepositoryProvider).markAllRead();
    } catch (_) {
      // Thất bại → khôi phục trạng thái trước để badge phản ánh đúng.
      state = previous;
    }
  }

  String _messageOf(Object e) {
    try {
      final dynamic dyn = e;
      final message = dyn.message;
      if (message is String && message.isNotEmpty) return message;
    } catch (_) {}
    return 'Không tải được thông báo. Vui lòng thử lại.';
  }
}

final notificationsControllerProvider =
    StateNotifierProvider<NotificationsController, NotificationsState>((ref) {
      return NotificationsController(ref);
    });
