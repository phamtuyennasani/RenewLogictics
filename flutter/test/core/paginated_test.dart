import 'package:flutter_test/flutter_test.dart';
import 'package:shipper_ops_app/core/models/paginated.dart';

void main() {
  group('Paginated.fromData', () {
    test('parse items + meta đầy đủ', () {
      final page = Paginated.fromData(
        {
          'items': [
            {'id': 1},
            {'id': 2},
          ],
          'meta': {
            'current_page': 2,
            'per_page': 15,
            'total': 40,
            'last_page': 3,
            'has_more': true,
          },
        },
        (json) => json['id'] as int,
      );

      expect(page.items, [1, 2]);
      expect(page.currentPage, 2);
      expect(page.perPage, 15);
      expect(page.total, 40);
      expect(page.lastPage, 3);
      expect(page.hasMore, isTrue);
    });

    test('thiếu meta → fallback an toàn', () {
      final page = Paginated.fromData(
        {
          'items': [
            {'id': 1},
          ],
        },
        (json) => json['id'] as int,
      );

      expect(page.items, [1]);
      expect(page.currentPage, 1);
      expect(page.hasMore, isFalse);
    });

    test('items không phải list → rỗng', () {
      final page = Paginated.fromData(
        {'items': null, 'meta': {}},
        (json) => json['id'] as int,
      );

      expect(page.items, isEmpty);
    });
  });
}
