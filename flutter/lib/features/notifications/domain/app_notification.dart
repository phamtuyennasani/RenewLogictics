class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.content,
    required this.excerpt,
    required this.author,
    required this.createdAt,
    required this.isRead,
  });

  final int id;
  final String title;
  final String content;
  final String excerpt;
  final String author;
  final DateTime? createdAt;
  final bool isRead;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: (json['title'] ?? '').toString(),
      content: (json['content'] ?? '').toString(),
      excerpt: (json['excerpt'] ?? json['content'] ?? '').toString(),
      author: (json['author'] ?? 'Hệ thống').toString(),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      isRead: json['is_read'] == true,
    );
  }

  AppNotification copyWith({bool? isRead}) {
    return AppNotification(
      id: id,
      title: title,
      content: content,
      excerpt: excerpt,
      author: author,
      createdAt: createdAt,
      isRead: isRead ?? this.isRead,
    );
  }
}
