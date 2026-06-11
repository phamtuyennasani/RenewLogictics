import 'package:flutter_test/flutter_test.dart';
import 'package:shipper_ops_app/core/api/api_envelope.dart';

void main() {
  group('ApiEnvelope', () {
    test('parse envelope thành công với data object', () {
      final env = ApiEnvelope.fromJson({
        'success': true,
        'message': 'OK',
        'data': {'id': 1, 'name': 'abc'},
      });

      expect(env.success, isTrue);
      expect(env.message, 'OK');
      expect(env.dataMap['id'], 1);
      expect(env.firstError, isNull);
    });

    test('parse validation errors (422) thành Map<String, List<String>>', () {
      final env = ApiEnvelope.fromJson({
        'success': false,
        'message': 'Dữ liệu không hợp lệ',
        'errors': {
          'username': ['Bắt buộc nhập'],
          'password': ['Tối thiểu 6 ký tự', 'Sai định dạng'],
        },
      });

      expect(env.success, isFalse);
      expect(env.errors!['password'], hasLength(2));
      expect(env.firstError, isNotNull);
    });

    test('errors dạng chuỗi đơn được bọc thành list', () {
      final env = ApiEnvelope.fromJson({
        'success': false,
        'message': 'Lỗi',
        'errors': {'field': 'một lỗi'},
      });

      expect(env.errors!['field'], ['một lỗi']);
    });

    test('dataMap trả map rỗng khi data không phải object', () {
      final env = ApiEnvelope.fromJson({
        'success': true,
        'message': '',
        'data': [1, 2, 3],
      });

      expect(env.dataMap, isEmpty);
    });
  });
}
