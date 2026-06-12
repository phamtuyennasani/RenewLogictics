import 'package:geolocator/geolocator.dart';

/// Toạ độ đơn giản (lat/lng) — tách khỏi kiểu của geolocator/vietmap để domain
/// không phụ thuộc package cụ thể.
class LatLngPoint {
  const LatLngPoint(this.lat, this.lng);

  final double lat;
  final double lng;

  @override
  bool operator ==(Object other) =>
      other is LatLngPoint && other.lat == lat && other.lng == lng;

  @override
  int get hashCode => Object.hash(lat, lng);
}

/// Lỗi định vị có thông điệp thân thiện + cờ cho biết có nên mở Settings không.
class LocationFailure implements Exception {
  const LocationFailure(this.message, {this.openSettings = false});

  final String message;

  /// true → quyền bị từ chối vĩnh viễn / dịch vụ tắt: nên gợi ý mở Settings.
  final bool openSettings;

  @override
  String toString() => message;
}

/// Bọc geolocator: xin quyền + lấy vị trí hiện tại, ném [LocationFailure] với
/// thông điệp tiếng Việt rõ ràng khi thất bại.
class LocationService {
  const LocationService();

  /// Lấy vị trí hiện tại. Tự xử lý dịch vụ định vị + quyền trước khi đọc.
  Future<LatLngPoint> currentPosition() async {
    await _ensureServiceEnabled();
    await _ensurePermission();

    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 12),
        ),
      );
      return LatLngPoint(position.latitude, position.longitude);
    } catch (_) {
      // Thử lại với độ chính xác thấp hơn (trong nhà / GPS yếu).
      try {
        final position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.medium,
            timeLimit: Duration(seconds: 12),
          ),
        );
        return LatLngPoint(position.latitude, position.longitude);
      } catch (_) {
        throw const LocationFailure(
          'Không lấy được vị trí. Vui lòng ra nơi thoáng và thử lại.',
        );
      }
    }
  }

  Future<void> _ensureServiceEnabled() async {
    final enabled = await Geolocator.isLocationServiceEnabled();
    if (!enabled) {
      throw const LocationFailure(
        'Dịch vụ định vị đang tắt. Vui lòng bật GPS rồi thử lại.',
        openSettings: true,
      );
    }
  }

  Future<void> _ensurePermission() async {
    var permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.deniedForever) {
      throw const LocationFailure(
        'Quyền vị trí đã bị từ chối. Vui lòng cấp quyền trong Cài đặt.',
        openSettings: true,
      );
    }

    if (permission == LocationPermission.denied) {
      throw const LocationFailure(
        'Cần quyền vị trí để chỉ đường tới điểm lấy hàng.',
      );
    }
  }

  /// Mở màn hình cài đặt ứng dụng để người dùng cấp quyền thủ công.
  Future<void> openAppSettings() => Geolocator.openAppSettings();
}
