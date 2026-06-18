import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/location/location_service.dart';
import '../../../core/models/status_badge.dart';
import '../data/pending_status_store.dart';
import '../domain/pickup.dart';
import '../domain/pickup_repository.dart';
import 'pickup_providers.dart';

/// State chi tiết một pickup.
class PickupDetailState {
  const PickupDetailState({
    this.detail,
    this.isLoading = true,
    this.isSubmitting = false,
    this.isLocating = false,
    this.isPendingSync = false,
    this.errorMessage,
    this.errorOpenSettings = false,
    this.actionMessage,
  });

  final PickupDetail? detail;
  final bool isLoading;
  final bool isSubmitting;

  /// Đang lấy GPS trước khi gửi đổi trạng thái (UI hiện "Đang lấy vị trí...").
  final bool isLocating;

  /// Có thao tác đổi trạng thái đã xếp hàng chờ đồng bộ (offline).
  final bool isPendingSync;
  final String? errorMessage;

  /// true → lỗi vị trí cần mở Cài đặt (quyền/GPS tắt).
  final bool errorOpenSettings;

  /// Message thành công sau khi đổi trạng thái (để UI show SnackBar).
  final String? actionMessage;

  PickupDetailState copyWith({
    PickupDetail? detail,
    bool? isLoading,
    bool? isSubmitting,
    bool? isLocating,
    bool? isPendingSync,
    String? errorMessage,
    bool? errorOpenSettings,
    bool clearError = false,
    String? actionMessage,
    bool clearAction = false,
  }) {
    return PickupDetailState(
      detail: detail ?? this.detail,
      isLoading: isLoading ?? this.isLoading,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      isLocating: isLocating ?? this.isLocating,
      isPendingSync: isPendingSync ?? this.isPendingSync,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      errorOpenSettings:
          clearError ? false : (errorOpenSettings ?? this.errorOpenSettings),
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
  LocationService get _location => ref.read(locationServiceProvider);

  /// Trạng thái yêu cầu GPS bắt buộc khi check-in (đồng bộ backend).
  static const _requiresGps = 'pickup_da_lay';

  late int _pickupId;

  @override
  PickupDetailState build(int arg) {
    _pickupId = arg;
    Future.microtask(load);

    // Khi bộ đồng bộ offline xử lý xong action của pickup này → reload chi tiết
    // (status mới từ server) và tắt cờ chờ đồng bộ.
    final syncSub = ref.read(pendingStatusSyncProvider).syncedStream.listen((id) {
      if (id == _pickupId) {
        state = state.copyWith(isPendingSync: false);
        load();
      }
    });
    ref.onDispose(syncSub.cancel);

    // Nếu pickup này còn thao tác chờ đồng bộ từ trước → phản ánh lên UI.
    final pending = ref.read(pendingStatusStoreProvider).hasPending(arg);
    return PickupDetailState(isLoading: true, isPendingSync: pending);
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
  /// GPS check-in: khi chuyển sang [_requiresGps] (đã lấy hàng) thì BẮT BUỘC
  /// lấy được toạ độ — không lấy được thì dừng, không đổi trạng thái. Các trạng
  /// thái khác cố lấy GPS "best effort", không chặn.
  ///
  /// Trả `true` nếu thành công để UI điều hướng/refresh list.
  Future<bool> changeStatus({
    required String status,
    String? reason,
  }) async {
    final requiresGps = status == _requiresGps;

    // Bước 1: lấy GPS.
    double? lat;
    double? lng;
    state = state.copyWith(isLocating: true, clearError: true, clearAction: true);
    try {
      final pos = await _location.currentPosition();
      lat = pos.lat;
      lng = pos.lng;
    } on LocationFailure catch (e) {
      if (requiresGps) {
        // Bắt buộc mà không lấy được → dừng, báo lỗi (kèm mở Cài đặt nếu cần).
        state = state.copyWith(
          isLocating: false,
          errorMessage: e.message,
          errorOpenSettings: e.openSettings,
        );
        return false;
      }
      // Không bắt buộc → bỏ qua, gửi không kèm toạ độ.
    } on Object {
      if (requiresGps) {
        state = state.copyWith(
          isLocating: false,
          errorMessage: 'Không lấy được vị trí. Vui lòng thử lại.',
        );
        return false;
      }
    }

    // Bước 2: kiểm tra mạng. Offline → xếp hàng chờ đồng bộ (optimistic).
    state = state.copyWith(isLocating: false);
    final online = await ref.read(connectivityServiceProvider).isOnline();
    if (!online) {
      await ref.read(pendingStatusStoreProvider).upsert(
            PendingStatusAction(
              pickupId: _pickupId,
              status: status,
              reason: reason,
              lat: lat,
              lng: lng,
              queuedAtMs: DateTime.now().millisecondsSinceEpoch,
            ),
          );
      // Optimistic: phản ánh trạng thái đích lên UI ngay (lấy badge từ
      // allowed_transitions hiện có) + ẩn nút đổi tiếp tới khi đồng bộ xong.
      final current = state.detail;
      var optimistic = current;
      if (current != null) {
        StatusBadge? target;
        for (final b in current.pickup.allowedTransitions) {
          if (b.value == status) {
            target = b;
            break;
          }
        }
        if (target != null) {
          optimistic = current.copyWith(
            pickup: current.pickup.copyWith(
              status: target,
              allowedTransitions: const [],
            ),
          );
        }
      }
      state = state.copyWith(
        detail: optimistic,
        isPendingSync: true,
        actionMessage: 'Đang offline — đã lưu, sẽ tự đồng bộ khi có mạng.',
      );
      return true;
    }

    // Bước 3: online → gửi đổi trạng thái.
    state = state.copyWith(isSubmitting: true);
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
      // Gửi thành công → bỏ pending cũ (nếu có) cho pickup này.
      await ref.read(pendingStatusStoreProvider).removeByPickup(_pickupId);
      final fresh = await _repo.detail(_pickupId);
      state = state.copyWith(
        detail: fresh,
        isSubmitting: false,
        isPendingSync: false,
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
