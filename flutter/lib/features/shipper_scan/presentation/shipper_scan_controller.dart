import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shipper_pickup/domain/pickup.dart';
import '../data/recent_pickup_scans_store.dart';
import '../domain/shipper_scan_repository.dart';
import '../domain/shipper_scan_result.dart';
import 'shipper_scan_providers.dart';

/// State màn hình quét nhận pickup của shipper.
class ShipperScanState {
  const ShipperScanState({
    this.result,
    this.recent = const [],
    this.isLooking = false,
    this.isReceiving = false,
    this.errorMessage,
    this.receivedMessage,
  });

  /// Kết quả quét gần nhất (null = chưa quét gì).
  final ShipperScanResult? result;

  /// Lịch sử quét trong phiên (mới nhất trước).
  final List<RecentPickupScan> recent;

  /// Đang tra cứu mã (gọi /shipper/scan).
  final bool isLooking;

  /// Đang nhận hàng (gọi /shipper/pickups/receive-by-scan).
  final bool isReceiving;

  final String? errorMessage;

  /// Message thành công sau khi nhận hàng (UI show SnackBar).
  final String? receivedMessage;

  bool get isBusy => isLooking || isReceiving;

  ShipperScanState copyWith({
    ShipperScanResult? result,
    bool clearResult = false,
    List<RecentPickupScan>? recent,
    bool? isLooking,
    bool? isReceiving,
    String? errorMessage,
    bool clearError = false,
    String? receivedMessage,
    bool clearReceived = false,
  }) {
    return ShipperScanState(
      result: clearResult ? null : (result ?? this.result),
      recent: recent ?? this.recent,
      isLooking: isLooking ?? this.isLooking,
      isReceiving: isReceiving ?? this.isReceiving,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      receivedMessage:
          clearReceived ? null : (receivedMessage ?? this.receivedMessage),
    );
  }
}

/// Controller màn quét nhận pickup: tra cứu mã kiện, nhận hàng, ghi lịch sử.
class ShipperScanController extends Notifier<ShipperScanState> {
  ShipperScanRepository get _repo => ref.read(shipperScanRepositoryProvider);
  RecentPickupScansStore get _store => ref.read(recentPickupScansStoreProvider);

  @override
  ShipperScanState build() {
    return ShipperScanState(recent: _store.load());
  }

  /// Tra cứu một mã kiện. Luồng "không tìm thấy" là bình thường (found=false),
  /// vẫn ghi vào lịch sử để shipper biết đã quét.
  Future<void> lookup(String rawCode) async {
    final code = rawCode.trim();
    if (code.isEmpty || state.isBusy) return;

    state = state.copyWith(
      isLooking: true,
      clearError: true,
      clearReceived: true,
    );
    try {
      final result = await _repo.scan(code);
      state = state.copyWith(result: result, isLooking: false);

      if (!result.found) {
        await _recordScan(
          code: code,
          received: false,
          note: result.reason ?? 'Không tìm thấy pickup khớp mã.',
        );
      }
    } on Object catch (e) {
      state = state.copyWith(isLooking: false, errorMessage: _messageOf(e));
    }
  }

  /// Nhận hàng pickup đang hiển thị ở [ShipperScanState.result].
  ///
  /// Trả `true` nếu thành công. Sau khi nhận, ghi lịch sử và cập nhật kết quả
  /// hiện tại sang trạng thái mới (không cho nhận lại).
  Future<bool> receiveCurrent() async {
    final result = state.result;
    final pickup = result?.pickup;
    final orderId = result?.orderId;
    if (pickup == null ||
        orderId == null ||
        !(result?.canReceive ?? false) ||
        state.isBusy) {
      return false;
    }

    state = state.copyWith(
      isReceiving: true,
      clearError: true,
      clearReceived: true,
    );
    try {
      final received = await _repo.receiveByScan(
        pickupId: pickup.id,
        orderId: orderId,
      );

      await _recordScan(
        code: result!.packageCode,
        received: true,
        pickupCode: pickup.maPickup,
        statusLabel: received.status.label,
      );

      // Cập nhật result sang trạng thái mới: đã nhận, không cho nhận lại.
      final updatedPickup = Pickup(
        id: pickup.id,
        maPickup: pickup.maPickup,
        status: received.status,
        customer: pickup.customer,
        location: pickup.location,
        allowedTransitions: const [],
        scheduledAt: pickup.scheduledAt,
        packageCount: pickup.packageCount,
        weightKg: pickup.weightKg,
        ordersCount: pickup.ordersCount,
        note: pickup.note,
        createdBy: pickup.createdBy,
      );
      state = state.copyWith(
        result: ShipperScanResult(
          found: true,
          canReceive: false,
          packageCode: result.packageCode,
          orderId: orderId,
          orderCode: result.orderCode,
          pickup: updatedPickup,
          reason: null,
        ),
        isReceiving: false,
        receivedMessage:
            received.message ?? 'Đã nhận hàng pickup ${pickup.maPickup}.',
      );
      return true;
    } on Object catch (e) {
      state = state.copyWith(isReceiving: false, errorMessage: _messageOf(e));
      return false;
    }
  }

  /// Xóa kết quả hiện tại để chuẩn bị quét mã mới.
  void clearResult() {
    state = state.copyWith(
      clearResult: true,
      clearError: true,
      clearReceived: true,
    );
  }

  void clearMessages() {
    if (state.errorMessage != null || state.receivedMessage != null) {
      state = state.copyWith(clearError: true, clearReceived: true);
    }
  }

  /// Xóa toàn bộ lịch sử quét trong phiên.
  Future<void> clearHistory() async {
    await _store.clear();
    state = state.copyWith(recent: const []);
  }

  Future<void> _recordScan({
    required String code,
    required bool received,
    String? pickupCode,
    String? statusLabel,
    String? note,
  }) async {
    final updated = await _store.add(
      RecentPickupScan(
        code: code,
        scannedAt: DateTime.now(),
        received: received,
        pickupCode: pickupCode,
        statusLabel: statusLabel,
        note: note,
      ),
    );
    state = state.copyWith(recent: updated);
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

final shipperScanControllerProvider =
    NotifierProvider<ShipperScanController, ShipperScanState>(
  ShipperScanController.new,
);
