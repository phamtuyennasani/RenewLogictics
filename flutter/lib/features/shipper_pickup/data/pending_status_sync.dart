// Constructor giữ tên param rõ nghĩa (store/repository/connectivity) thay vì
// initializing formal trần (_store...), nên tắt lint này cho cả file.
// ignore_for_file: prefer_initializing_formals
import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/network/connectivity_service.dart';
import '../domain/pickup_repository.dart';
import 'pending_status_store.dart';

/// Đồng bộ hàng đợi đổi trạng thái pickup khi có mạng trở lại.
///
/// Lắng nghe [ConnectivityService.onlineStream]; mỗi khi chuyển sang online thì
/// [drain] toàn bộ action đang chờ. Cũng nên gọi [drain] một lần lúc khởi động
/// (sau khi đăng nhập) để xử lý action còn sót từ phiên trước.
class PendingStatusSync {
  PendingStatusSync({
    required PendingStatusStore store,
    required PickupRepository repository,
    required ConnectivityService connectivity,
  })  : _store = store,
        _repo = repository,
        _connectivity = connectivity;

  final PendingStatusStore _store;
  final PickupRepository _repo;
  final ConnectivityService _connectivity;

  StreamSubscription<bool>? _sub;
  bool _draining = false;

  /// Phát pickupId mỗi khi một action của pickup đó được xử lý xong (gửi thành
  /// công hoặc bị bỏ do conflict) → màn detail đang mở lắng nghe để tự refresh.
  final _syncedController = StreamController<int>.broadcast();
  Stream<int> get syncedStream => _syncedController.stream;

  /// Số action bị bỏ do conflict/không hợp lệ ở lần drain gần nhất (cho UI báo).
  int lastDroppedCount = 0;

  /// Bắt đầu lắng nghe mạng. Gọi sau khi user đã đăng nhập.
  void start() {
    _sub ??= _connectivity.onlineStream.listen((online) {
      if (online) {
        // Fire-and-forget; lỗi nuốt trong drain.
        unawaited(drain());
      }
    });
  }

  /// Ngừng lắng nghe (vd khi logout).
  Future<void> stop() async {
    await _sub?.cancel();
    _sub = null;
  }

  void _notifySynced(int pickupId) {
    if (!_syncedController.isClosed) {
      _syncedController.add(pickupId);
    }
  }

  /// Gửi tuần tự các action đang chờ. Bỏ action conflict/không hợp lệ; giữ lại
  /// action lỗi mạng / token để thử lại lần sau.
  Future<void> drain() async {
    if (_draining) return;
    _draining = true;
    lastDroppedCount = 0;

    try {
      for (final action in _store.all()) {
        try {
          await _repo.updateStatus(
            pickupId: action.pickupId,
            status: action.status,
            reason: action.reason,
            lat: action.lat,
            lng: action.lng,
          );
          // Thành công → bỏ khỏi hàng đợi + báo màn đang mở refresh.
          await _store.removeByPickup(action.pickupId);
          _notifySynced(action.pickupId);
        } on ApiException catch (e) {
          if (_shouldDrop(e)) {
            // Trạng thái đã đổi / không hợp lệ / không tìm thấy → bỏ, không retry.
            await _store.removeByPickup(action.pickupId);
            lastDroppedCount++;
            _notifySynced(action.pickupId);
          } else if (e.kind == ApiErrorKind.unauthorized) {
            // Token hết hạn → dừng drain, giữ nguyên hàng đợi cho lần sau.
            break;
          } else {
            // Lỗi mạng/server tạm thời → giữ lại, dừng để thử lại lần online sau.
            break;
          }
        } catch (_) {
          // Lỗi lạ → giữ lại, dừng.
          break;
        }
      }
    } finally {
      _draining = false;
    }

    if (kDebugMode && lastDroppedCount > 0) {
      debugPrint('[PendingStatusSync] bỏ $lastDroppedCount action do conflict.');
    }
  }

  /// Lỗi "vĩnh viễn" với action này → bỏ khỏi hàng đợi.
  static bool _shouldDrop(ApiException e) {
    return e.kind == ApiErrorKind.conflict ||
        e.kind == ApiErrorKind.validation ||
        e.kind == ApiErrorKind.notFound;
  }
}
