import '../domain/scan_repository.dart';
import '../domain/scan_result.dart';
import 'scan_api.dart';

/// Triển khai [ScanRepository]: gọi [ScanApi], map envelope → domain.
class ScanRepositoryImpl implements ScanRepository {
  ScanRepositoryImpl(this._api);

  final ScanApi _api;

  @override
  Future<ScanResult> scan(String code) async {
    final env = await _api.scan(code);
    return ScanResult.fromJson(env.dataMap);
  }

  @override
  Future<ReceiveResult> receive({required int orderId, String? code}) async {
    final env = await _api.receive(orderId: orderId, code: code);
    return ReceiveResult.fromJson(env.dataMap, message: env.message);
  }

  @override
  Future<BulkReceiveResult> bulkReceiveByCodes(List<String> codes) async {
    final env = await _api.bulkReceiveByCodes(codes);
    return BulkReceiveResult.fromJson(env.dataMap, message: env.message);
  }
}
