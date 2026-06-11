import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Tên thiết bị gửi kèm khi login (`device_name` — contract §2.1),
/// dùng làm tên Sanctum token để dễ quản lý/thu hồi.
final deviceNameProvider = FutureProvider<String>((ref) async {
  final info = DeviceInfoPlugin();
  try {
    if (Platform.isAndroid) {
      final a = await info.androidInfo;
      return '${a.manufacturer} ${a.model}'.trim();
    }
    if (Platform.isIOS) {
      final i = await info.iosInfo;
      return i.name.isNotEmpty ? i.name : (i.utsname.machine);
    }
  } catch (_) {
    // ignore — fallback bên dưới.
  }
  return 'mobile';
});
