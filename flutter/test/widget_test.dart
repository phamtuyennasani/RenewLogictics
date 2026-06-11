import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shipper_ops_app/core/models/status_badge.dart';
import 'package:shipper_ops_app/shared/widgets/status_chip.dart';

void main() {
  testWidgets('StatusChip hiển thị label của badge', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: StatusChip(
            badge: StatusBadge(value: 'da_xac_nhan', label: 'Đã xác nhận'),
          ),
        ),
      ),
    );

    expect(find.text('Đã xác nhận'), findsOneWidget);
  });

  testWidgets('StatusChip dùng màu từ StatusPalette theo value, không crash với value lạ',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: StatusChip(
            badge: StatusBadge(value: 'value_khong_ton_tai', label: 'Lạ'),
          ),
        ),
      ),
    );

    expect(find.text('Lạ'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
