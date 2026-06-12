import '../../../core/models/paginated.dart';
import 'ops_order.dart';

/// Repository trừu tượng cho OPS orders.
abstract class OpsOrderRepository {
  /// Lấy danh sách order được giao cho OPS.
  Future<Paginated<OpsOrder>> list({
    String? keyword,
    String? status,
    bool? hasPickup,
    int page = 1,
    int perPage = 15,
  });

  /// Lấy chi tiết một order.
  Future<OpsOrderDetail> detail(int orderId);

  /// Tạo phiếu pickup từ order.
  Future<Map<String, dynamic>> createPickup(int orderId, Map<String, dynamic> data);
}
