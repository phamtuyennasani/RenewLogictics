/// Wrapper phân trang theo contract §1.5:
/// `data: { items: [...], meta: { current_page, per_page, total, last_page, has_more } }`.
class Paginated<T> {
  const Paginated({
    required this.items,
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
    required this.hasMore,
    this.unreadCount,
  });

  final List<T> items;
  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;
  final bool hasMore;

  /// Số chưa đọc toàn bộ (chỉ có ở endpoint thông báo; null ở nơi khác).
  final int? unreadCount;

  /// Parse từ `data` (object chứa `items` + `meta`).
  factory Paginated.fromData(
    Map<String, dynamic> data,
    T Function(Map<String, dynamic>) itemFromJson,
  ) {
    final rawItems = data['items'];
    final items = rawItems is List
        ? rawItems.whereType<Map<String, dynamic>>().map(itemFromJson).toList()
        : <T>[];

    final meta = data['meta'] is Map<String, dynamic>
        ? data['meta'] as Map<String, dynamic>
        : const <String, dynamic>{};

    return Paginated<T>(
      items: items,
      currentPage: _int(meta['current_page'], 1),
      perPage: _int(meta['per_page'], items.length),
      total: _int(meta['total'], items.length),
      lastPage: _int(meta['last_page'], 1),
      hasMore: meta['has_more'] == true,
      unreadCount: meta.containsKey('unread_count')
          ? _int(meta['unread_count'], 0)
          : null,
    );
  }

  static int _int(Object? value, int fallback) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }
}
