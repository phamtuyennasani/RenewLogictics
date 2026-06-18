import '../../../core/models/paginated.dart';
import '../domain/pickup.dart';
import '../domain/pickup_image.dart';
import '../domain/pickup_repository.dart';
import 'pickup_api.dart';

/// Triển khai [PickupRepository]: gọi [PickupApi], map envelope → domain.
class PickupRepositoryImpl implements PickupRepository {
  PickupRepositoryImpl(this._api);

  final PickupApi _api;

  @override
  Future<PickupListPage> list({
    PickupTab? tab,
    String? status,
    String? keyword,
    int page = 1,
    int perPage = 15,
  }) async {
    final env = await _api.list(
      tab: tab?.query,
      status: status,
      keyword: keyword,
      page: page,
      perPage: perPage,
    );

    final data = env.dataMap;
    return PickupListPage(
      summary: PickupSummary.fromJson(
        data['summary'] is Map<String, dynamic>
            ? data['summary'] as Map<String, dynamic>
            : null,
      ),
      page: Paginated<Pickup>.fromData(data, Pickup.fromJson),
    );
  }

  @override
  Future<PickupDetail> detail(int pickupId) async {
    final env = await _api.detail(pickupId);
    return PickupDetail.fromJson(env.dataMap);
  }

  @override
  Future<PickupDetail> updateStatus({
    required int pickupId,
    required String status,
    String? reason,
    double? lat,
    double? lng,
  }) async {
    final env = await _api.updateStatus(
      pickupId: pickupId,
      status: status,
      reason: reason,
      lat: lat,
      lng: lng,
    );
    // Response §3.3 trả id + status + allowed_transitions (subset của detail).
    // Parse linh hoạt: nếu có orders thì full detail, không thì dựng từ subset.
    return PickupDetail.fromJson(env.dataMap);
  }

  @override
  Future<List<PickupImage>> listImages(int pickupId) async {
    final env = await _api.listImages(pickupId);
    return PickupImage.listFrom(env.dataMap['items']);
  }

  @override
  Future<PickupImage> uploadImage(int pickupId, String filePath) async {
    final env = await _api.uploadImage(pickupId, filePath);
    return PickupImage.fromJson(env.dataMap);
  }

  @override
  Future<void> deleteImage(int pickupId, int imageId) async {
    await _api.deleteImage(pickupId, imageId);
  }
}
