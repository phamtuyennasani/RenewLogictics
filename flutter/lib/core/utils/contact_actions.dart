import 'package:url_launcher/url_launcher.dart';

/// Tiện ích mở ứng dụng ngoài: gọi điện, mở bản đồ chỉ đường.
class ContactActions {
  const ContactActions._();

  /// Gọi điện tới [phone]. Trả false nếu không mở được.
  static Future<bool> call(String phone) async {
    final sanitized = phone.replaceAll(RegExp(r'[^0-9+]'), '');
    if (sanitized.isEmpty) return false;
    final uri = Uri(scheme: 'tel', path: sanitized);
    return _launch(uri);
  }

  /// Mở bản đồ chỉ đường tới toạ độ. Dùng geo: trên Android, Apple/Google Maps fallback.
  static Future<bool> openMap({
    required double lat,
    required double lng,
    String? label,
  }) async {
    final safeLabel = label?.trim();
    final geoLabel = safeLabel != null && safeLabel.isNotEmpty
        ? '(${Uri.encodeComponent(safeLabel)})'
        : '';

    return _launchAny([
      Uri.parse('geo:0,0?q=$lat,$lng$geoLabel'),
      Uri.parse('https://www.google.com/maps/search/?api=1&query=$lat,$lng'),
    ]);
  }

  /// Mở app bản đồ ngoài ở chế độ DẪN ĐƯỜNG (turn-by-turn) tới toạ độ đích.
  ///
  /// Khác [openMap] (chỉ đánh dấu điểm): hàm này yêu cầu app bản đồ điều hướng
  /// từ vị trí hiện tại của thiết bị tới đích. Dùng deep link Google Maps
  /// `dir_action=navigate` — mở thẳng Google Maps (Android/iOS nếu có cài),
  /// fallback sang web khi chưa cài app.
  static Future<bool> openDirections({
    required double lat,
    required double lng,
  }) async {
    return _launchAny([
      Uri.parse('google.navigation:q=$lat,$lng&mode=d'),
      Uri.parse(
        'https://www.google.com/maps/dir/?api=1'
        '&destination=$lat,$lng&travelmode=driving&dir_action=navigate',
      ),
    ]);
  }

  static Future<bool> _launch(Uri uri) async {
    try {
      return await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      return false;
    }
  }

  static Future<bool> _launchAny(List<Uri> uris) async {
    for (final uri in uris) {
      if (await _launch(uri)) {
        return true;
      }
    }
    return false;
  }
}
