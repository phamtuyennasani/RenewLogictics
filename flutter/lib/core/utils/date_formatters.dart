import 'package:intl/intl.dart';

/// Định dạng ngày giờ / số cho UI.
///
/// API trả ISO 8601 có timezone (contract §1.7); các model đã parse sẵn sang
/// [DateTime]. Vì vậy các hàm nhận [DateTime?] trực tiếp. Có thêm biến thể
/// `*Iso` nhận chuỗi ISO khi cần format thẳng từ response.
class DateFormatters {
  const DateFormatters._();

  static final DateFormat _dateTime = DateFormat('dd/MM/yyyy HH:mm', 'vi');
  static final DateFormat _date = DateFormat('dd/MM/yyyy', 'vi');
  static final DateFormat _time = DateFormat('HH:mm', 'vi');

  /// Parse ISO string sang local DateTime, null nếu rỗng/sai.
  static DateTime? parse(String? iso) {
    if (iso == null || iso.isEmpty) return null;
    return DateTime.tryParse(iso)?.toLocal();
  }

  // ----- Nhận DateTime (dùng phổ biến vì model đã parse) -----

  static String dateTime(DateTime? value, {String fallback = '—'}) {
    if (value == null) return fallback;
    return _dateTime.format(value.toLocal());
  }

  static String date(DateTime? value, {String fallback = '—'}) {
    if (value == null) return fallback;
    return _date.format(value.toLocal());
  }

  static String time(DateTime? value, {String fallback = '—'}) {
    if (value == null) return fallback;
    return _time.format(value.toLocal());
  }

  // ----- Nhận chuỗi ISO (khi format thẳng từ response) -----

  static String dateTimeIso(String? iso, {String fallback = '—'}) =>
      dateTime(parse(iso), fallback: fallback);

  static String dateIso(String? iso, {String fallback = '—'}) =>
      date(parse(iso), fallback: fallback);

  static String timeIso(String? iso, {String fallback = '—'}) =>
      time(parse(iso), fallback: fallback);

  /// Cân nặng (kg) — bỏ phần thập phân thừa.
  static String weight(num? value, {String fallback = '—'}) {
    if (value == null) return fallback;
    final s = value % 1 == 0
        ? value.toStringAsFixed(0)
        : value.toStringAsFixed(2);
    return '$s kg';
  }
}
