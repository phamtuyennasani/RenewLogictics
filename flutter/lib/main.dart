import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app/app.dart';
import 'app/env.dart';
import 'core/notifications/push_providers.dart';
import 'core/providers.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Nạp cấu hình môi trường (.env). Không chặn app nếu thiếu/lỗi file ở dev;
  // Env tự fallback sang giá trị mặc định khi dotenv chưa init.
  try {
    await dotenv.load(fileName: '.env');
  } catch (e) {
    debugPrint(
      '[main] Không nạp được .env ($e) → dùng giá trị mặc định trong Env.',
    );
  }

  // Khởi tạo dữ liệu locale cho intl (định dạng ngày giờ tiếng Việt).
  await initializeDateFormatting('vi');

  // DEV: cho phép tải tài nguyên HTTPS cert tự ký (vd Image.network ảnh bằng
  // chứng từ backend local). Dio đã tự bỏ qua bad cert riêng, nhưng HttpClient
  // mặc định của Flutter (Image.network...) thì không → cài override toàn cục.
  // Gated bởi Env.allowBadCert: chỉ bật ở debug + backend local, production tự tắt.
  if (Env.allowBadCert) {
    HttpOverrides.global = _DevHttpOverrides();
  }

  // SharedPreferences cho dữ liệu không nhạy cảm (lịch sử scan trong phiên).
  final prefs = await SharedPreferences.getInstance();

  final container = ProviderContainer(
    overrides: [sharedPreferencesProvider.overrideWithValue(prefs)],
  );

  // Render UI ngay, KHÔNG chặn bởi push init.
  //
  // Trên iOS simulator, FirebaseMessaging.getInitialMessage() chờ APNS token
  // vô thời hạn (simulator không có môi trường APNS) → nếu await trước runApp
  // thì main() treo và app trắng màn hình. Push được khởi tạo nền, fire-and-forget;
  // route từ notification mở-khi-terminated được giữ lại (_pendingRoute) và flush
  // khi UI gắn onRouteSelected, nên không mất sự kiện tap.
  unawaited(container.read(pushNotificationServiceProvider).init());

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const ShipperOpsApp(),
    ),
  );
}

/// DEV ONLY: bỏ qua xác thực cert tự ký cho mọi HttpClient mặc định của Flutter
/// (Image.network, v.v.). Chỉ được cài khi [Env.allowBadCert] = true.
class _DevHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) {
    return super.createHttpClient(context)
      ..badCertificateCallback = (cert, host, port) => true;
  }
}
