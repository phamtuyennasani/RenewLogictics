import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../domain/pickup.dart';
import '../domain/pickup_repository.dart';
import 'pickup_providers.dart';

/// State chi tiết một pickup.
class PickupDetailState {
  const PickupDetailState({
    this.detail,
    this.isLoading = true,
    this.isSubmitting = false,
    this.errorMessage,
    this.actionMessage,
  });

  final PickupDetail? detail;
  final bool isLoading;
  final bool isSubmitting;
  final String? errorMessage;

  /// Message thành công sau khi đổi trạng thái (để UI show SnackBar).
  final String? actionMessage;

  PickupDetailState copyWith({
    PickupDetail? detail,
    bool? isLoading,
    bool? isSubmitting,
    String? errorMessage,
    bool clearError = false,
    String? actionMessage,
    bool clearAction = false,
  }) {
    return PickupDetailState(
      detail: detail ?? this.detail,
      isLoading: isLoading ?? this.isLoading,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      actionMessage: clearAction ? null : (actionMessage ?? this.actionMessage),
    );
  }
}

/// Controller chi tiết pickup: tải chi tiết + đổi trạng thái (FSM theo API).
///
/// Dùng family theo pickupId để mỗi màn detail có state riêng.
class PickupDetailController
    extends FamilyNotifier<PickupDetailState, int> {
  PickupRepository get _repo => ref.read(pickupRepositoryProvider);

  late int _pickupId;

  @override
  PickupDetailState build(int arg) {
    _pickupId = arg;
    Future.microtask(load);
    return const PickupDetailState(isLoading: true);
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final detail = await _repo.detail(_pickupId);
      state = state.copyWith(detail: detail, isLoading: false);
    } on Object catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: _messageOf(e));
    }
  }

  /// Đổi trạng thái. [status] phải nằm trong allowed_transitions (UI đã lọc).
  /// [reason] bắt buộc khi hủy (UI ép nhập trước khi gọi).
  ///
  /// Trả `true` nếu thành công để UI điều hướng/refresh list.
  Future<bool> changeStatus({
    required String status,
    String? reason,
    double? lat,
    double? lng,
  }) async {
    state = state.copyWith(isSubmitting: true, clearError: true, clearAction: true);
    try {
      // Response §3.3 chỉ trả subset (id + status + allowed_transitions),
      // KHÔNG có customer/orders/location. Nếu gán thẳng sẽ xóa thông tin
      // đang hiển thị → reload full detail để giữ đầy đủ dữ liệu.
      await _repo.updateStatus(
        pickupId: _pickupId,
        status: status,
        reason: reason,
        lat: lat,
        lng: lng,
      );
      final fresh = await _repo.detail(_pickupId);
      state = state.copyWith(
        detail: fresh,
        isSubmitting: false,
        actionMessage: 'Đã cập nhật trạng thái.',
      );
      return true;
    } on Object catch (e) {
      state = state.copyWith(isSubmitting: false, errorMessage: _messageOf(e));
      return false;
    }
  }

  void clearMessages() {
    if (state.errorMessage != null || state.actionMessage != null) {
      state = state.copyWith(clearError: true, clearAction: true);
    }
  }

  static String _messageOf(Object e) {
    try {
      final dynamic dyn = e;
      final m = dyn.message;
      if (m is String && m.isNotEmpty) return m;
    } catch (_) {}
    return 'Thao tác thất bại. Vui lòng thử lại.';
  }
}

final pickupDetailControllerProvider = NotifierProvider.family<
    PickupDetailController, PickupDetailState, int>(
  PickupDetailController.new,
);
