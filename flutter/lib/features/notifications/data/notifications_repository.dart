import '../../../core/models/paginated.dart';
import '../domain/app_notification.dart';
import 'notifications_api.dart';

class NotificationsRepository {
  const NotificationsRepository(this._api);

  final NotificationsApi _api;

  Future<Paginated<AppNotification>> list({
    int page = 1,
    int perPage = 30,
  }) async {
    final envelope = await _api.list(page: page, perPage: perPage);
    return Paginated.fromData(envelope.dataMap, AppNotification.fromJson);
  }

  Future<AppNotification> getById(int id) async {
    final envelope = await _api.show(id);
    return AppNotification.fromJson(envelope.dataMap);
  }

  Future<void> markRead(int id) async {
    await _api.markRead(id);
  }

  Future<void> markAllRead() async {
    await _api.markAllRead();
  }
}
