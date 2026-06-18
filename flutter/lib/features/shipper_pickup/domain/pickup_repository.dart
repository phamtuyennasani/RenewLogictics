import '../../../core/models/paginated.dart';
import 'pickup.dart';
import 'pickup_image.dart';

/// Tab danh sách pickup (contract §6.1) → map sang status filter.
enum PickupTab {
  newPickup('new', 'Mới', 'moi_tao_pickup'),
  accepted('accepted', 'Đã nhận', 'da_xac_nhan'),
  picking('picking', 'Đang lấy', 'pickup_dang_lay'),
  done('done', 'Đã lấy', 'pickup_da_lay');

  const PickupTab(this.query, this.label, this.statusValue);

  /// Giá trị gửi lên param `tab`.
  final String query;

  /// Nhãn hiển thị trên TabBar.
  final String label;

  /// Status value tương ứng (tham chiếu, backend tự map từ `tab`).
  final String statusValue;
}

/// Summary header (contract §3.1 `summary`).
class PickupSummary {
  const PickupSummary({this.pendingCount = 0, this.nearestScheduleAt});

  final int pendingCount;
  final DateTime? nearestScheduleAt;

  factory PickupSummary.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const PickupSummary();
    return PickupSummary(
      pendingCount: (json['pending_count'] as num?)?.toInt() ?? 0,
      nearestScheduleAt: json['nearest_schedule_at'] != null
          ? DateTime.tryParse(json['nearest_schedule_at'].toString())
          : null,
    );
  }
}

/// Kết quả danh sách pickup: summary + trang dữ liệu.
class PickupListPage {
  const PickupListPage({required this.summary, required this.page});

  final PickupSummary summary;
  final Paginated<Pickup> page;
}

/// Kết quả cập nhật status (contract §3.3).
class PickupStatusUpdate {
  const PickupStatusUpdate({required this.detail});

  final PickupDetail detail;
}

/// Contract repository pickup — presentation chỉ phụ thuộc abstraction này.
abstract class PickupRepository {
  /// Danh sách pickup của shipper đang đăng nhập.
  Future<PickupListPage> list({
    PickupTab? tab,
    String? status,
    String? keyword,
    int page = 1,
    int perPage = 15,
  });

  /// Chi tiết một pickup.
  Future<PickupDetail> detail(int pickupId);

  /// Cập nhật trạng thái pickup. [reason] bắt buộc khi hủy.
  Future<PickupDetail> updateStatus({
    required int pickupId,
    required String status,
    String? reason,
    double? lat,
    double? lng,
  });

  /// Danh sách ảnh bằng chứng của một pickup.
  Future<List<PickupImage>> listImages(int pickupId);

  /// Upload một ảnh bằng chứng (đường dẫn file local) → trả ảnh vừa lưu.
  Future<PickupImage> uploadImage(int pickupId, String filePath);

  /// Xóa một ảnh bằng chứng do shipper upload.
  Future<void> deleteImage(int pickupId, int imageId);
}
