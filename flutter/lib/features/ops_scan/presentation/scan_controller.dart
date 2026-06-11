import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/recent_scans_store.dart';
import '../domain/scan_repository.dart';
import '../domain/scan_result.dart';
import 'scan_providers.dart';

/// State màn hình quét OPS.
class ScanState {
  const ScanState({
    this.result,
    this.recent = const [],
    this.isLooking = false,
    this.isReceiving = false,
    this.errorMessage,
    this.receivedMessage,
  });

  /// Kết quả tra cứu mã gần nhất (null = chưa quét gì).
  final ScanResult? result;

  /// Lịch sử scan trong phiên (mới nhất trước).
  final List<RecentScan> recent;

  /// Đang tra cứu mã (gọi /ops/scan).
  final bool isLooking;

  /// Đang xác nhận nhập kho (gọi /receive).
  final bool isReceiving;

  final String? errorMessage;

  /// Message thành công sau khi nhập kho (để UI show SnackBar).
  final String? receivedMessage;

  bool get isBusy => isLooking || isReceiving;

  ScanState copyWith({
    ScanResult? result,
    bool clearResult = false,
    List<RecentScan>? recent,
    bool? isLooking,
    bool? isReceiving,
    String? errorMessage,
    bool clearError = false,
    String? receivedMessage,
    bool clearReceived = false,
  }) {
    return ScanState(
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

/// Controller màn quét OPS: tra cứu mã, nhập kho, ghi lịch sử phiên.
class ScanController extends Notifier<ScanState> {
  ScanRepository get _repo => ref.read(scanRepositoryProvider);
  RecentScansStore get _store => ref.read(recentScansStoreProvider);

  @override
  ScanState build() {
    // Nạp lịch sử đã lưu (nếu có) ngay khi khởi tạo.
    return ScanState(recent: _store.load());
  }

  /// Tra cứu một mã quét. Luồng "không tìm thấy" là bình thường (found=false),
  /// không phải lỗi — vẫn ghi vào lịch sử để OPS biết đã quét.
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

      // Nếu không tìm thấy đơn → ghi nhận vào lịch sử (received=false).
      if (!result.found) {
        await _recordScan(
          code: code,
          received: false,
          note: result.reason ?? 'Không tìm thấy đơn khớp mã.',
        );
      }
    } on Object catch (e) {
      state = state.copyWith(isLooking: false, errorMessage: _messageOf(e));
    }
  }

  /// Xác nhận nhập kho đơn đang hiển thị ở [ScanState.result].
  ///
  /// Trả `true` nếu thành công. Sau khi nhập kho, ghi lịch sử và cập nhật
  /// kết quả hiện tại sang trạng thái mới (không cho nhập lại).
  Future<bool> receiveCurrent() async {
    final result = state.result;
    final order = result?.order;
    if (order == null || !(result?.canReceive ?? false) || state.isBusy) {
      return false;
    }

    state = state.copyWith(
      isReceiving: true,
      clearError: true,
      clearReceived: true,
    );
    try {
      final received = await _repo.receive(
        orderId: order.id,
        code: order.primaryCode,
      );

      await _recordScan(
        code: order.primaryCode,
        received: true,
        orderId: received.orderId,
        idBill: received.idBill ?? order.idBill,
        statusLabel: received.status.label,
      );

      // Cập nhật result sang trạng thái mới: đã nhận, không cho nhập lại.
      final updatedOrder = ScanOrder(
        id: order.id,
        status: received.status,
        idBill: order.idBill,
        trackingCode: order.trackingCode,
        mathamchieu: order.mathamchieu,
        sender: order.sender,
        receiver: order.receiver,
        packageCount: order.packageCount,
        weightKg: order.weightKg,
        saleName: order.saleName,
        locked: order.locked,
        receivedAt: received.receivedAt ?? DateTime.now(),
      );
      state = state.copyWith(
        result: ScanResult(
          found: true,
          canReceive: false,
          matchedBy: result!.matchedBy,
          matchedPackageCode: result.matchedPackageCode,
          order: updatedOrder,
          reason: null,
        ),
        isReceiving: false,
        receivedMessage: received.message ?? 'Đã nhập kho ${order.primaryCode}.',
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

  /// Xóa toàn bộ lịch sử scan trong phiên.
  Future<void> clearHistory() async {
    await _store.clear();
    state = state.copyWith(recent: const []);
  }

  Future<void> _recordScan({
    required String code,
    required bool received,
    int? orderId,
    String? idBill,
    String? statusLabel,
    String? note,
  }) async {
    final updated = await _store.add(
      RecentScan(
        code: code,
        scannedAt: DateTime.now(),
        received: received,
        orderId: orderId,
        idBill: idBill,
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

final scanControllerProvider =
    NotifierProvider<ScanController, ScanState>(ScanController.new);
