import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import 'mobile_config_api.dart';

/// DI cho cấu hình public của Mobile API.
final mobileConfigApiProvider = Provider<MobileConfigApi>((ref) {
  return MobileConfigApi(ref.watch(dioClientProvider));
});

/// Nạp [MobileConfig] một lần và cache lại trong vòng đời app.
///
/// Dùng cho VietMap tile key (render bản đồ). Giữ alive để tránh gọi lại mỗi
/// lần mở màn hình bản đồ; key hiếm khi đổi trong một phiên.
final mobileConfigProvider = FutureProvider<MobileConfig>((ref) async {
  ref.keepAlive();
  return ref.watch(mobileConfigApiProvider).fetch();
});
