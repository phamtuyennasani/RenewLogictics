import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../../firebase_options.dart';

/// Dữ liệu định tuyến rút ra từ payload push (contract Phase 6: tối thiểu).
class PushRoute {
  const PushRoute({
    required this.type,
    this.pickupId,
    this.pickupCode,
    this.orderId,
    this.orderCode,
    this.newsId,
  });

  final String type;
  final int? pickupId;
  final String? pickupCode;
  final int? orderId;
  final String? orderCode;
  final int? newsId;

  static PushRoute? fromData(Map<String, dynamic> data) {
    final type = (data['type'] ?? '').toString();
    if (type.isEmpty) return null;

    final rawPickupId = data['pickup_id']?.toString();
    final rawOrderId = data['order_id']?.toString();
    final rawNewsId = data['news_id']?.toString();

    return PushRoute(
      type: type,
      pickupId: rawPickupId == null ? null : int.tryParse(rawPickupId),
      pickupCode: data['pickup_code']?.toString(),
      orderId: rawOrderId == null ? null : int.tryParse(rawOrderId),
      orderCode: data['id_bill']?.toString(),
      newsId: rawNewsId == null ? null : int.tryParse(rawNewsId),
    );
  }
}

/// Handler nền (top-level bắt buộc với firebase_messaging).
/// Chỉ cần khởi tạo Firebase; không cần làm gì thêm vì OS đã hiển thị
/// notification từ phần `notification` của payload.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Isolate nền cần init Firebase riêng trước khi dùng bất kỳ API nào.
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  // Không thao tác UI ở đây. Tap sẽ được xử lý qua getInitialMessage/onMessageOpenedApp.
}

/// Quản lý vòng đời push notification (FCM + local notification).
///
/// Thiết kế degrade an toàn: nếu chưa cấu hình Firebase (thiếu
/// google-services.json / GoogleService-Info.plist) thì [init] bắt lỗi và
/// đánh dấu [isAvailable] = false; toàn bộ API khác trở thành no-op, app vẫn chạy.
class PushNotificationService {
  PushNotificationService({
    this._messaging,
    FlutterLocalNotificationsPlugin? localNotifications,
  }) : _local = localNotifications ?? FlutterLocalNotificationsPlugin();

  FirebaseMessaging? _messaging;
  final FlutterLocalNotificationsPlugin _local;

  bool _available = false;
  bool _initialized = false;

  /// Callback do app gắn vào để điều hướng khi user tap notification.
  void Function(PushRoute route)? onRouteSelected;

  /// Callback khi nhận message lúc app đang foreground (chưa tap). Dùng để
  /// cập nhật state nền như badge số chưa đọc mà không điều hướng.
  void Function(PushRoute route)? onMessageReceived;

  final _androidChannel = const AndroidNotificationChannel(
    'pickup_alerts',
    'Thông báo Pickup',
    description: 'Thông báo khi có pickup được giao cho shipper.',
    importance: Importance.high,
  );

  bool get isAvailable => _available;

  /// Khởi tạo Firebase + local notifications. Gọi một lần lúc bootstrap.
  /// An toàn khi chưa có config: chỉ log và tắt tính năng.
  Future<void> init() async {
    if (_initialized) return;
    _initialized = true;

    try {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
      _messaging ??= FirebaseMessaging.instance;
      _available = true;
    } catch (e) {
      debugPrint('[Push] Firebase chưa cấu hình → tắt push ($e).');
      _available = false;
      return;
    }

    try {
      await _setupLocalNotifications();

      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      // Foreground: tự hiển thị local notification (FCM không tự hiện khi foreground).
      FirebaseMessaging.onMessage.listen(_onForegroundMessage);

      // Tap khi app ở background và được đưa lên foreground.
      FirebaseMessaging.onMessageOpenedApp.listen(_onMessageOpened);
    } catch (e) {
      debugPrint('[Push] Lỗi khi cấu hình listener/local notifications ($e).');
    }

    // Tap khi app bị terminated rồi mở từ notification.
    //
    // Fire-and-forget: trên iOS simulator getInitialMessage() có thể chờ APNS
    // token rất lâu/vô hạn. Không await để không treo init; route (nếu có) được
    // giữ lại trong _pendingRoute và flush khi UI gắn onRouteSelected.
    unawaited(
      _messaging!
          .getInitialMessage()
          .then((initial) {
            if (initial != null) _onMessageOpened(initial);
          })
          .catchError((Object e) {
            debugPrint('[Push] getInitialMessage lỗi ($e).');
          }),
    );
  }

  Future<void> _setupLocalNotifications() async {
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosInit = DarwinInitializationSettings(
      requestAlertPermission: false,
      requestBadgePermission: false,
      requestSoundPermission: false,
    );

    await _local.initialize(
      const InitializationSettings(android: androidInit, iOS: iosInit),
      onDidReceiveNotificationResponse: (response) {
        final payload = response.payload;
        if (payload != null) {
          _emitRouteFromData(_decodePayload(payload));
        }
      },
    );

    await _local
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >()
        ?.createNotificationChannel(_androidChannel);
  }

  /// Xin quyền nhận notification (iOS bắt buộc; Android 13+ cũng cần).
  Future<bool> requestPermission() async {
    if (!_available || _messaging == null) return false;
    final settings = await _messaging!.requestPermission();
    return settings.authorizationStatus == AuthorizationStatus.authorized ||
        settings.authorizationStatus == AuthorizationStatus.provisional;
  }

  /// Lấy FCM token hiện tại (null nếu chưa có quyền/chưa cấu hình).
  Future<String?> getToken() async {
    if (!_available || _messaging == null) return null;
    try {
      return await _messaging!.getToken();
    } catch (e) {
      debugPrint('[Push] Không lấy được FCM token ($e).');
      return null;
    }
  }

  /// Stream token refresh để app đăng ký lại lên server.
  Stream<String> get onTokenRefresh {
    if (!_available || _messaging == null) {
      return const Stream<String>.empty();
    }
    return _messaging!.onTokenRefresh;
  }

  void _onForegroundMessage(RemoteMessage message) {
    // Báo cho app cập nhật state nền (badge...) dù user chưa tap.
    final route = PushRoute.fromData(message.data);
    if (route != null) {
      onMessageReceived?.call(route);
    }

    final notification = message.notification;
    if (notification == null) return;

    _local.show(
      notification.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _androidChannel.id,
          _androidChannel.name,
          channelDescription: _androidChannel.description,
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: const DarwinNotificationDetails(),
      ),
      payload: _encodePayload(message.data),
    );
  }

  void _onMessageOpened(RemoteMessage message) {
    _emitRouteFromData(message.data);
  }

  void _emitRouteFromData(Map<String, dynamic> data) {
    final route = PushRoute.fromData(data);
    if (route != null) {
      onRouteSelected?.call(route);
    }
  }

  /// Mã hóa data payload thành chuỗi cho local notification payload.
  /// Dùng định dạng đơn giản key=value;... (data toàn string từ FCM).
  String _encodePayload(Map<String, dynamic> data) {
    return data.entries
        .map((e) => '${e.key}=${Uri.encodeComponent(e.value.toString())}')
        .join(';');
  }

  Map<String, dynamic> _decodePayload(String payload) {
    final map = <String, dynamic>{};
    for (final pair in payload.split(';')) {
      final idx = pair.indexOf('=');
      if (idx <= 0) continue;
      final key = pair.substring(0, idx);
      final value = Uri.decodeComponent(pair.substring(idx + 1));
      map[key] = value;
    }
    return map;
  }
}
