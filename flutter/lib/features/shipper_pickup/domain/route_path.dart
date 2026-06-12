import '../../../core/location/location_service.dart';

/// Tuyến đường trả về từ VietMap routing (`paths[0]`).
class RoutePath {
  const RoutePath({
    required this.coordinates,
    required this.distanceMeters,
    required this.durationMs,
  });

  /// Danh sách điểm tuyến, đã chuẩn hoá về (lat, lng).
  final List<LatLngPoint> coordinates;

  /// Tổng quãng đường (mét).
  final double distanceMeters;

  /// Tổng thời gian (mili giây).
  final double durationMs;

  bool get isEmpty => coordinates.isEmpty;

  /// Parse `paths[0]` của response VietMap `route/v3` (points_encoded=false).
  ///
  /// VietMap có thể trả `points.coordinates` (GeoJSON) hoặc `points` trực tiếp;
  /// mỗi điểm là `[lng, lat]` hoặc `[lat, lng]` tuỳ phiên bản → chuẩn hoá lại
  /// giống logic web (shipper-pickup-route-map.js#routeCoordinates).
  factory RoutePath.fromVietmapPath(Map<String, dynamic> path) {
    final points = path['points'];
    final rawList = points is Map<String, dynamic>
        ? points['coordinates']
        : points;

    final coords = <LatLngPoint>[];
    if (rawList is List) {
      for (final point in rawList) {
        if (point is! List || point.length < 2) continue;
        final a = (point[0] as num?)?.toDouble();
        final b = (point[1] as num?)?.toDouble();
        if (a == null || b == null) continue;

        // Nếu phần tử đầu nằm trong [-90,90] và phần tử sau trong [-180,180]
        // → đây là [lat, lng]; ngược lại là [lng, lat].
        final isLatLng = a.abs() <= 90 && b.abs() <= 180;
        coords.add(isLatLng ? LatLngPoint(a, b) : LatLngPoint(b, a));
      }
    }

    return RoutePath(
      coordinates: coords,
      distanceMeters: (path['distance'] as num?)?.toDouble() ?? 0,
      durationMs: (path['time'] as num?)?.toDouble() ?? 0,
    );
  }
}
