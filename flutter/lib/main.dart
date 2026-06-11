import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'app/app.dart';
import 'core/providers.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Nạp cấu hình môi trường (.env). Không chặn app nếu thiếu/lỗi file ở dev;
  // Env tự fallback sang giá trị mặc định khi dotenv chưa init.
  try {
    await dotenv.load(fileName: '.env');
  } catch (e) {
    debugPrint('[main] Không nạp được .env ($e) → dùng giá trị mặc định trong Env.');
  }

  // Khởi tạo dữ liệu locale cho intl (định dạng ngày giờ tiếng Việt).
  await initializeDateFormatting('vi');

  // SharedPreferences cho dữ liệu không nhạy cảm (lịch sử scan trong phiên).
  final prefs = await SharedPreferences.getInstance();

  runApp(
    ProviderScope(
      overrides: [
        sharedPreferencesProvider.overrideWithValue(prefs),
      ],
      child: const ShipperOpsApp(),
    ),
  );
}
