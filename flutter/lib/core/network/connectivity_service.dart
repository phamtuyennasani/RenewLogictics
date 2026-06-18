import 'package:connectivity_plus/connectivity_plus.dart';

/// Bọc connectivity_plus: cho biết thiết bị có kết nối mạng hay không.
///
/// connectivity_plus 6.x trả `List<ConnectivityResult>` (một thiết bị có thể có
/// nhiều kết nối cùng lúc). Coi là "online" khi có ít nhất một kết nối khác
/// [ConnectivityResult.none].
///
/// LƯU Ý: connectivity chỉ báo có kết nối ở tầng mạng, KHÔNG đảm bảo internet
/// thật sự ra ngoài được. Đủ tốt cho hàng đợi offline (retry khi có mạng lại).
class ConnectivityService {
  ConnectivityService([Connectivity? connectivity])
      : _connectivity = connectivity ?? Connectivity();

  final Connectivity _connectivity;

  static bool _isOnline(List<ConnectivityResult> results) {
    return results.any((r) => r != ConnectivityResult.none);
  }

  /// Kiểm tra trạng thái mạng hiện tại.
  Future<bool> isOnline() async {
    final results = await _connectivity.checkConnectivity();
    return _isOnline(results);
  }

  /// Stream phát `true/false` mỗi khi trạng thái mạng đổi.
  Stream<bool> get onlineStream =>
      _connectivity.onConnectivityChanged.map(_isOnline);
}
