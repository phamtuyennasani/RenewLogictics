import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:shipper_ops_app/features/shipper_pickup/data/pending_status_store.dart';

void main() {
  late PendingStatusStore store;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    store = PendingStatusStore(prefs);
  });

  PendingStatusAction action(int pickupId, String status, {double? lat}) {
    return PendingStatusAction(
      pickupId: pickupId,
      status: status,
      lat: lat,
      lng: lat,
      queuedAtMs: 1000,
    );
  }

  group('PendingStatusStore', () {
    test('rỗng ban đầu', () {
      expect(store.all(), isEmpty);
      expect(store.hasPending(1), isFalse);
    });

    test('upsert thêm action + round-trip giữ dữ liệu', () async {
      await store.upsert(action(1, 'pickup_dang_lay', lat: 21.0));
      final items = store.all();
      expect(items, hasLength(1));
      expect(items.first.pickupId, 1);
      expect(items.first.status, 'pickup_dang_lay');
      expect(items.first.lat, 21.0);
      expect(store.hasPending(1), isTrue);
    });

    test('upsert cùng pickup → ghi đè, không nhân đôi', () async {
      await store.upsert(action(1, 'pickup_dang_lay'));
      await store.upsert(action(1, 'pickup_da_lay'));
      final items = store.all();
      expect(items, hasLength(1));
      expect(items.first.status, 'pickup_da_lay');
    });

    test('upsert pickup khác → giữ cả hai', () async {
      await store.upsert(action(1, 'pickup_dang_lay'));
      await store.upsert(action(2, 'da_huy'));
      expect(store.all(), hasLength(2));
      expect(store.hasPending(1), isTrue);
      expect(store.hasPending(2), isTrue);
    });

    test('removeByPickup chỉ xóa đúng pickup', () async {
      await store.upsert(action(1, 'pickup_dang_lay'));
      await store.upsert(action(2, 'da_huy'));
      await store.removeByPickup(1);
      expect(store.hasPending(1), isFalse);
      expect(store.hasPending(2), isTrue);
      expect(store.all(), hasLength(1));
    });

    test('clear xóa hết', () async {
      await store.upsert(action(1, 'pickup_dang_lay'));
      await store.upsert(action(2, 'da_huy'));
      await store.clear();
      expect(store.all(), isEmpty);
    });
  });
}
