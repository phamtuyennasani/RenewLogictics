import 'package:flutter_test/flutter_test.dart';
import 'package:shipper_ops_app/core/models/status_badge.dart';
import 'package:shipper_ops_app/core/utils/status_palette.dart';

void main() {
  group('StatusBadge', () {
    test('fromJson lấy value/label, giữ rawColor (Tailwind)', () {
      final b = StatusBadge.fromJson({
        'value': 'da_xac_nhan',
        'label': 'Đã xác nhận',
        'color': 'bg-blue-100 text-blue-700',
      });
      expect(b.value, 'da_xac_nhan');
      expect(b.label, 'Đã xác nhận');
      expect(b.rawColor, 'bg-blue-100 text-blue-700');
    });

    test('listFrom parse danh sách transition (chỉ value/label)', () {
      final list = StatusBadge.listFrom([
        {'value': 'pickup_dang_lay', 'label': 'Đang lấy'},
        {'value': 'da_huy', 'label': 'Đã huỷ'},
      ]);
      expect(list, hasLength(2));
      expect(list[0].value, 'pickup_dang_lay');
      expect(list[1].value, 'da_huy');
    });

    test('listFrom với input không phải list → rỗng', () {
      expect(StatusBadge.listFrom(null), isEmpty);
      expect(StatusBadge.listFrom('x'), isEmpty);
    });
  });

  group('StatusPalette', () {
    test('map các status pickup sang màu khác nhau', () {
      final a = StatusPalette.of('da_xac_nhan');
      final b = StatusPalette.of('pickup_da_lay');
      expect(a.bg, isNot(equals(b.bg)));
    });

    test('status lạ → màu default (không crash)', () {
      final p = StatusPalette.of('khong_ton_tai');
      expect(p.bg, isNotNull);
      expect(p.fg, isNotNull);
    });
  });
}
