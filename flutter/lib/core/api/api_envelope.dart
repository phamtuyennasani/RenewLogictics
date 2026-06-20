/// Envelope chuẩn của Mobile API (contract §1.3).
///
/// Thành công: `{ "success": true, "message": "...", "data": {...} }`
/// Thất bại:  `{ "success": false, "message": "...", "errors": {field: [..]} }`
class ApiEnvelope {
  const ApiEnvelope({
    required this.success,
    required this.message,
    this.data,
    this.errors,
  });

  final bool success;
  final String message;
  final Object? data;

  /// Validation errors dạng `{ field: [messages] }` (chỉ có ở 422).
  final Map<String, List<String>>? errors;

  factory ApiEnvelope.fromJson(Map<String, dynamic> json) {
    Map<String, List<String>>? parsedErrors;
    final rawErrors = json['errors'];
    if (rawErrors is Map) {
      parsedErrors = rawErrors.map(
        (key, value) => MapEntry(
          key.toString(),
          value is List
              ? value.map((e) => e.toString()).toList()
              : <String>[value.toString()],
        ),
      );
    }

    return ApiEnvelope(
      success: json['success'] == true,
      message: (json['message'] ?? '').toString(),
      data: json['data'],
      errors: parsedErrors,
    );
  }

  /// `data` ép về Map (object response). Trả map rỗng nếu không phải object.
  Map<String, dynamic> get dataMap => data is Map<String, dynamic>
      ? data! as Map<String, dynamic>
      : <String, dynamic>{};

  /// First error message của field bất kỳ (tiện hiển thị nhanh).
  String? get firstError {
    if (errors == null || errors!.isEmpty) return null;
    final first = errors!.values.firstWhere(
      (list) => list.isNotEmpty,
      orElse: () => const [],
    );
    return first.isEmpty ? null : first.first;
  }
}
