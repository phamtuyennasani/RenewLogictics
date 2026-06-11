import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:flutter/foundation.dart';

import '../device/device_name_provider.dart';
import '../providers.dart';
import 'device_token_api.dart';
import 'push_notification_service.dart';

/// Service push (singleton toàn app).
final pushNotificationServiceProvider = Provider<PushNotificationService>((
  ref,
) {
  return PushNotificationService();
});

/// API đăng ký / thu hồi device token.
final deviceTokenApiProvider = Provider<DeviceTokenApi>((ref) {
  return DeviceTokenApi(ref.watch(dioClientProvider));
});

/// Điều phối vòng đời push token theo phiên đăng nhập.
///
/// - [registerCurrentDevice] gọi sau khi đăng nhập thành công.
/// - [revokeCurrentDevice] gọi trước khi đăng xuất.
/// Tự lắng nghe token refresh để đăng ký lại.
class PushRegistration {
  PushRegistration(this._ref);

  final Ref _ref;
  StreamSubscription<String>? _refreshSub;
  String? _lastToken;

  PushNotificationService get _service =>
      _ref.read(pushNotificationServiceProvider);

  DeviceTokenApi get _api => _ref.read(deviceTokenApiProvider);

  /// Xin quyền, lấy token, gửi lên server. Lắng nghe refresh.
  Future<void> registerCurrentDevice() async {
    if (!_service.isAvailable) return;

    final granted = await _service.requestPermission();
    if (!granted) {
      debugPrint('[Push] Người dùng từ chối quyền notification.');
      return;
    }

    final token = await _service.getToken();
    if (token != null) {
      await _sendToken(token);
    }

    _refreshSub ??= _service.onTokenRefresh.listen(_sendToken);
  }

  Future<void> _sendToken(String token) async {
    _lastToken = token;
    try {
      final deviceName = await _ref.read(deviceNameProvider.future);
      final info = await PackageInfo.fromPlatform();
      await _api.register(
        fcmToken: token,
        platform: defaultTargetPlatform.name,
        deviceName: deviceName,
        appVersion: '${info.version}+${info.buildNumber}',
      );
    } catch (e) {
      debugPrint('[Push] Đăng ký token thất bại ($e).');
    }
  }

  /// Thu hồi token thiết bị hiện tại (gọi trước logout).
  Future<void> revokeCurrentDevice() async {
    final token = _lastToken ?? await _service.getToken();
    if (token == null) return;
    try {
      await _api.revoke(token);
    } catch (e) {
      debugPrint('[Push] Thu hồi token thất bại ($e).');
    }
    _lastToken = null;
  }

  void dispose() {
    _refreshSub?.cancel();
    _refreshSub = null;
  }
}

final pushRegistrationProvider = Provider<PushRegistration>((ref) {
  final registration = PushRegistration(ref);
  ref.onDispose(registration.dispose);
  return registration;
});
