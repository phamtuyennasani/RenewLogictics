import 'package:flutter_test/flutter_test.dart';
import 'package:shipper_ops_app/features/ops_scan/domain/scan_result.dart';

void main() {
  group('ScanResult.fromJson', () {
    test('tìm thấy đơn, có thể nhập kho', () {
      final r = ScanResult.fromJson({
        'found': true,
        'can_receive': true,
        'matched_by': 'id_bill',
        'matched_package_code': 'PKG-1',
        'order': {
          'id': 50,
          'status': {'value': 'da_xac_nhan', 'label': 'Đã xác nhận'},
          'id_bill': 'BILL-50',
          'package_count': 2,
          'weight_kg': 5.0,
          'sender': {'fullname': 'Người gửi'},
          'receiver': {'company': 'Cty nhận'},
        },
      });

      expect(r.found, isTrue);
      expect(r.canReceive, isTrue);
      expect(r.matchedBy, ScanMatchedBy.idBill);
      expect(r.order!.primaryCode, 'BILL-50');
      expect(r.order!.sender!.displayName, 'Người gửi');
      expect(r.order!.receiver!.displayName, 'Cty nhận');
    });

    test('tìm thấy nhưng không nhập được → có reason', () {
      final r = ScanResult.fromJson({
        'found': true,
        'can_receive': false,
        'reason': 'Đơn đã được nhập kho trước đó',
        'order': {
          'id': 51,
          'status': {'value': 'da_nhan_hang', 'label': 'Đã nhận hàng'},
          'locked': true,
        },
      });

      expect(r.canReceive, isFalse);
      expect(r.reason, contains('nhập kho'));
      expect(r.order!.locked, isTrue);
    });

    test('không tìm thấy đơn', () {
      final r = ScanResult.fromJson({'found': false, 'can_receive': false});
      expect(r.found, isFalse);
      expect(r.order, isNull);
      expect(r.matchedBy, ScanMatchedBy.unknown);
    });

    test('không phơi bày trường tài chính (chỉ map field cho phép)', () {
      final r = ScanResult.fromJson({
        'found': true,
        'can_receive': true,
        'order': {
          'id': 1,
          'status': {'value': 'da_xac_nhan', 'label': 'x'},
          'id_bill': 'B1',
          // Backend không trả tiền; client cũng không có field để chứa.
        },
      });
      // ScanOrder không có bất kỳ getter tài chính nào — chỉ kiểm tra parse ok.
      expect(r.order!.idBill, 'B1');
    });
  });

  group('ReceiveResult.fromJson', () {
    test('parse kết quả nhập kho', () {
      final r = ReceiveResult.fromJson(
        {
          'order': {
            'id': 70,
            'status': {'value': 'da_nhan_hang', 'label': 'Đã nhận hàng'},
            'id_bill': 'B-70',
            'received_at': '2026-06-11T09:00:00+07:00',
          },
        },
        message: 'Nhập kho thành công',
      );

      expect(r.orderId, 70);
      expect(r.status.value, 'da_nhan_hang');
      expect(r.idBill, 'B-70');
      expect(r.receivedAt, isNotNull);
      expect(r.message, 'Nhập kho thành công');
    });
  });

  group('RecentScan', () {
    test('round-trip toJson/fromJson', () {
      final original = RecentScan(
        code: 'BILL-1',
        scannedAt: DateTime.parse('2026-06-11T09:00:00Z'),
        received: true,
        orderId: 5,
        idBill: 'BILL-1',
        statusLabel: 'Đã nhận hàng',
      );

      final restored = RecentScan.fromJson(original.toJson());

      expect(restored.code, original.code);
      expect(restored.received, isTrue);
      expect(restored.orderId, 5);
      expect(restored.scannedAt, original.scannedAt);
    });
  });
}
