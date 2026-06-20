import 'package:flutter_test/flutter_test.dart';
import 'package:shipper_ops_app/core/models/status_badge.dart';
import 'package:shipper_ops_app/features/shipper_pickup/domain/pickup.dart';
import 'package:shipper_ops_app/features/shipper_pickup/domain/pickup_image.dart';

void main() {
  group('Pickup.fromJson', () {
    test('parse item danh sách đầy đủ trường', () {
      final p = Pickup.fromJson({
        'id': 7,
        'ma_pickup': 'PU-001',
        'status': {
          'value': 'da_xac_nhan',
          'label': 'Đã xác nhận',
          'color': 'x',
        },
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
        'shipper': {'id': 11, 'name': 'Nguyễn Shipper'},
      });

      expect(p.id, 7);
      expect(p.maPickup, 'PU-001');
      expect(p.status.value, 'da_xac_nhan');
      expect(p.customer.displayName, 'Nguyễn A');
      expect(p.location.hasLocation, isTrue);
      expect(p.allowedTransitions, hasLength(2));
      expect(p.scheduledAt, isNotNull);
      expect(p.weightKg, 12.5);
      expect(p.shipper?.name, 'Nguyễn Shipper');
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

    test('parse shipper từ nested fullname hoặc field phẳng', () {
      final nested = Pickup.fromJson({
        'id': 1,
        'ma_pickup': 'PU',
        'status': {'value': 'moi_tao_pickup', 'label': 'Mới'},
        'shipper': {'id': 8, 'fullname': 'Trần Shipper'},
      });
      expect(nested.shipper?.name, 'Trần Shipper');

      final flat = Pickup.fromJson({
        'id': 2,
        'ma_pickup': 'PU-2',
        'status': {'value': 'moi_tao_pickup', 'label': 'Mới'},
        'id_shipper': 9,
        'shipper_name': 'Lê Shipper',
      });
      expect(flat.shipper?.name, 'Lê Shipper');
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

    test('parse images[] kèm chi tiết', () {
      final d = PickupDetail.fromJson({
        'id': 9,
        'ma_pickup': 'PU-9',
        'status': {'value': 'pickup_da_lay', 'label': 'Đã lấy'},
        'images': [
          {
            'id': 1,
            'url': 'https://h/uploads/pickup/9/a.jpg',
            'uploaded_at': '2026-06-12T09:00:00+07:00',
          },
          {'id': 2, 'url': 'https://h/uploads/pickup/9/b.jpg'},
        ],
      });

      expect(d.images, hasLength(2));
      expect(d.images.first.id, 1);
      expect(d.images.first.url, 'https://h/uploads/pickup/9/a.jpg');
      expect(d.images.first.uploadedAt, isNotNull);
      expect(d.images[1].uploadedAt, isNull);
    });

    test('thiếu images → danh sách rỗng an toàn', () {
      final d = PickupDetail.fromJson({
        'id': 3,
        'ma_pickup': 'PU-3',
        'status': {'value': 'moi_tao_pickup', 'label': 'Mới'},
      });
      expect(d.images, isEmpty);
    });

    test('parse checkin GPS khi có', () {
      final d = PickupDetail.fromJson({
        'id': 5,
        'ma_pickup': 'PU-5',
        'status': {'value': 'pickup_da_lay', 'label': 'Đã lấy'},
        'checkin': {
          'lat': 21.028,
          'lng': 105.804,
          'checkin_at': '2026-06-19T08:15:00+07:00',
        },
      });
      expect(d.checkin, isNotNull);
      expect(d.checkin!.lat, 21.028);
      expect(d.checkin!.lng, 105.804);
      expect(d.checkin!.at, isNotNull);
    });

    test('thiếu checkin / thiếu lat-lng → null an toàn', () {
      final d1 = PickupDetail.fromJson({
        'id': 6,
        'ma_pickup': 'PU-6',
        'status': {'value': 'moi_tao_pickup', 'label': 'Mới'},
      });
      expect(d1.checkin, isNull);

      final d2 = PickupDetail.fromJson({
        'id': 7,
        'ma_pickup': 'PU-7',
        'status': {'value': 'moi_tao_pickup', 'label': 'Mới'},
        'checkin': {'lat': 21.0},
      });
      expect(d2.checkin, isNull);
    });
  });

  group('Optimistic copyWith', () {
    PickupDetail sample() => PickupDetail.fromJson({
      'id': 9,
      'ma_pickup': 'PU-9',
      'status': {'value': 'da_xac_nhan', 'label': 'Đã xác nhận'},
      'allowed_transitions': [
        {'value': 'pickup_dang_lay', 'label': 'Đang lấy'},
      ],
      'orders': [
        {'id': 1, 'id_bill': 'B1'},
      ],
    });

    test('Pickup.copyWith đổi status + giữ field khác', () {
      final p = sample().pickup;
      final next = p.copyWith(
        status: const StatusBadge(value: 'pickup_dang_lay', label: 'Đang lấy'),
        allowedTransitions: const [],
      );
      expect(next.status.value, 'pickup_dang_lay');
      expect(next.allowedTransitions, isEmpty);
      // Field khác giữ nguyên.
      expect(next.id, p.id);
      expect(next.maPickup, p.maPickup);
    });

    test('PickupDetail.copyWith thay pickup + giữ orders', () {
      final d = sample();
      final next = d.copyWith(
        pickup: d.pickup.copyWith(allowedTransitions: const []),
      );
      expect(next.orders, hasLength(1));
      expect(next.pickup.allowedTransitions, isEmpty);
    });
  });

  group('PickupImage', () {
    test('fromJson giữ nguyên full URL', () {
      final img = PickupImage.fromJson({
        'id': 5,
        'url': 'https://h/x.png',
        'uploaded_at': '2026-06-12T09:00:00+07:00',
      });
      expect(img.id, 5);
      expect(img.url, 'https://h/x.png');
      expect(img.uploadedAt, isNotNull);
    });

    test('fromJson ưu tiên path tương đối → ghép host app', () {
      final img = PickupImage.fromJson({
        'id': 6,
        'path': '/uploads/pickup/9/a.jpg',
        'url': 'https://backend-khac.example/uploads/pickup/9/a.jpg',
      });
      // Resolve theo Env.apiBaseUrl (không phải host APP_URL của backend).
      expect(img.url, endsWith('/uploads/pickup/9/a.jpg'));
      expect(img.url, startsWith('http'));
      expect(img.url, isNot(contains('backend-khac.example')));
    });

    test('listFrom bỏ qua phần tử lạ / null an toàn', () {
      expect(PickupImage.listFrom(null), isEmpty);
      expect(PickupImage.listFrom('not-a-list'), isEmpty);
      final list = PickupImage.listFrom([
        {'id': 1, 'url': 'a'},
        'rác',
        {'id': 2, 'url': 'b'},
      ]);
      expect(list, hasLength(2));
      expect(list.last.id, 2);
    });
  });
}
