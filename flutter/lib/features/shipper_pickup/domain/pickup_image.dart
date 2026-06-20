import '../../../app/env.dart';

/// Ảnh bằng chứng pickup (contract: `data.images[]` / payload upload).
class PickupImage {
  const PickupImage({required this.id, required this.url, this.uploadedAt});

  final int id;
  final String url;
  final DateTime? uploadedAt;

  factory PickupImage.fromJson(Map<String, dynamic> json) {
    return PickupImage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      url: _resolveUrl(json['path'], json['url']),
      uploadedAt: json['uploaded_at'] != null
          ? DateTime.tryParse(json['uploaded_at'].toString())
          : null,
    );
  }

  /// Dựng URL ảnh khớp host app đang gọi.
  ///
  /// Ưu tiên `path` tương đối (vd `/uploads/pickup/..`) ghép `Env.apiBaseUrl`,
  /// để ảnh load đúng host (emulator/thiết bị thật khác host với APP_URL backend).
  /// Fallback `url` nếu backend chỉ trả full URL.
  static String _resolveUrl(Object? path, Object? url) {
    final p = path?.toString().trim() ?? '';
    if (p.isNotEmpty) {
      if (p.startsWith('http://') || p.startsWith('https://')) return p;
      return '${Env.apiBaseUrl}${p.startsWith('/') ? '' : '/'}$p';
    }
    final u = url?.toString().trim() ?? '';
    if (u.startsWith('http://') || u.startsWith('https://')) return u;
    if (u.isNotEmpty) {
      return '${Env.apiBaseUrl}${u.startsWith('/') ? '' : '/'}$u';
    }
    return '';
  }

  /// Parse mảng ảnh từ JSON (an toàn với null / kiểu lạ).
  static List<PickupImage> listFrom(Object? raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map<String, dynamic>>()
        .map(PickupImage.fromJson)
        .toList();
  }
}
