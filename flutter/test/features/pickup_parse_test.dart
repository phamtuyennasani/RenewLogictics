import 'package:flutter_test/flutter_test.dart';
import 'package:shipper_ops_app/features/shipper_pickup/domain/pickup.dart';

void main() {
  group('Pickup.fromJson', () {
    test('parse item danh sách đầy đủ trường', () {
      final p = Pickup.fromJson({
        'id': 7,
        'ma_pickup': 'PU-001',
        'status': {'value': 'da_xac_nhan', 'label': 'Đã xác nhận', 'color': 'x'},
        'customer': {'fullname': 'Nguyễn A', 'phone': '0900', 'address': 'HN'},
        'location': {'lat': 21.0, 'lng': 105.0, 'has_location': true},
        'allowed_transitions': [
          {'value': 'pickup_dang_lay', 'label': 'Đang lấy'},
          {'value': 'da_huy', 'label': 'Huỷ'},
        ],
        'scheduled_at': '2026-06-11T08:30:00+07:00',
        'package_count': 3,
        'weight_kg': 12.5,
        'orders_count': 2,
      });

      expect(p.id, 7);
      expect(p.maPickup, 'PU-001');
      expect(p.status.value, 'da_xac_nhan');
      expect(p.customer.displayName, 'Nguyễn A');
      expect(p.location.hasLocation, isTrue);
      expect(p.allowedTransitions, hasLength(2));
      expect(p.scheduledAt, isNotNull);
      expect(p.weightKg, 12.5);
    });

    test('thiếu trường tuỳ chọn → giá trị null/empty an toàn', () {
      final p = Pickup.fromJson({
        'id': 1,
        'ma_pickup': 'PU-X',
        'status': {'value': 'moi_tao_pickup', 'label': 'Mới'},
      });

      expect(p.allowedTransitions, isEmpty);
      expect(p.scheduledAt, isNull);
      expect(p.weightKg, isNull);
      expect(p.customer.displayName, 'Khách hàng');
    });

    test('customer ưu tiên company hơn fullname', () {
      final p = Pickup.fromJson({
        'id': 1,
        'ma_pickup': 'PU',
        'status': {'value': 'moi_tao_pickup', 'label': 'Mới'},
        'customer': {'company': 'Cty ABC', 'fullname': 'Nguyễn A'},
      });
      expect(p.customer.displayName, 'Cty ABC');
    });
  });

  group('PickupDetail.fromJson', () {
    test('parse orders[] lồng nhau', () {
      final d = PickupDetail.fromJson({
        'id': 9,
        'ma_pickup': 'PU-9',
        'status': {'value': 'pickup_dang_lay', 'label': 'Đang lấy'},
        'orders': [
          {'id': 100, 'id_bill': 'B-1', 'tracking_code': 'TRK1'},
          {'id': 101, 'tracking_code': 'TRK2'},
        ],
        'weight_gross_kg': 20.0,
        'created_at': '2026-06-10T10:00:00+07:00',
      });

      expect(d.pickup.id, 9);
      expect(d.orders, hasLength(2));
      expect(d.orders.first.idBill, 'B-1');
      expect(d.weightGrossKg, 20.0);
      expect(d.createdAt, isNotNull);
    });
  });
}
