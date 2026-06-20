import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../shared/widgets/app_surfaces.dart';
import '../../shipper_pickup/presentation/pickup_list_controller.dart';
import 'shipper_scan_controller.dart';
import 'widgets/shipper_scan_result_card.dart';

/// Màn quét mã kiện của shipper: quét Code128/QR → tìm pickup được gán → nhận
/// hàng. Tái dùng pattern OPS scan (mobile_scanner + Riverpod + cooldown).
class ShipperScannerScreen extends ConsumerStatefulWidget {
  const ShipperScannerScreen({super.key});

  @override
  ConsumerState<ShipperScannerScreen> createState() =>
      _ShipperScannerScreenState();
}

class _ShipperScannerScreenState extends ConsumerState<ShipperScannerScreen>
    with WidgetsBindingObserver {
  final MobileScannerController _scanner = MobileScannerController(
    autoStart: false,
    formats: const [BarcodeFormat.qrCode, BarcodeFormat.code128],
    detectionSpeed: DetectionSpeed.normal,
  );
  final _manualCtrl = TextEditingController();

  String? _lastCode;
  Timer? _cooldown;
  bool _torchOn = false;

  /// Camera mặc định tắt: người dùng chủ động bật khi cần, còn lại nhập tay.
  bool _cameraOn = false;

  /// Màn đang hiển thị hay bị che (theo TickerMode) — dừng camera khi ẩn.
  bool _visible = true;

  /// Đang trong một thao tác start/stop camera. Tránh gọi chồng lệnh khi lần
  /// đầu xin quyền: dialog quyền đẩy app sang paused → lifecycle gọi stop ngay
  /// giữa lúc start còn chờ, gây kẹt camera. Cờ này nối tiếp các thao tác.
  bool _busyCamera = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final visible = TickerMode.valuesOf(context).enabled;
    if (visible == _visible) return;
    _visible = visible;

    if (!_cameraOn) return;
    if (visible) {
      _resumeCamera();
    } else {
      _pauseCamera();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _cooldown?.cancel();
    _manualCtrl.dispose();
    _scanner.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Chỉ điều khiển camera khi người dùng đã bật và màn đang hiển thị.
    if (!_cameraOn || !_visible) return;

    switch (state) {
      case AppLifecycleState.resumed:
        _resumeCamera();
        break;
      case AppLifecycleState.inactive:
      case AppLifecycleState.paused:
      case AppLifecycleState.detached:
      case AppLifecycleState.hidden:
        _pauseCamera();
        break;
    }
  }

  /// Bật/tắt camera quét. Khi tắt, người dùng vẫn nhập mã thủ công bên dưới.
  Future<void> _toggleCamera() async {
    if (_cameraOn) {
      setState(() {
        _cameraOn = false;
        _torchOn = false;
      });
      await _pauseCamera();
    } else {
      setState(() => _cameraOn = true);
      await _resumeCamera();
    }
  }

  /// Start camera an toàn: nối tiếp thao tác, bỏ qua nếu đã chạy.
  Future<void> _resumeCamera() async {
    if (_busyCamera || _scanner.value.isRunning) return;
    _busyCamera = true;
    try {
      await _scanner.start();
    } catch (_) {
      // start() có thể ném khi đang khởi tạo hoặc quyền bị từ chối —
      // errorBuilder của MobileScanner sẽ hiển thị thông báo, không cần xử lý.
    } finally {
      _busyCamera = false;
    }
  }

  /// Stop camera an toàn: chỉ dừng khi đang chạy, nối tiếp thao tác.
  Future<void> _pauseCamera() async {
    if (_busyCamera || !_scanner.value.isRunning) return;
    _busyCamera = true;
    try {
      await _scanner.stop();
    } catch (_) {
      // Bỏ qua nếu camera đã dừng.
    } finally {
      _busyCamera = false;
    }
  }

  void _onDetect(BarcodeCapture capture) {
    if (ref.read(shipperScanControllerProvider).isBusy) return;
    final raw = capture.barcodes
        .map((b) => b.rawValue)
        .firstWhere((v) => v != null && v.isNotEmpty, orElse: () => null);
    if (raw == null) return;

    if (raw == _lastCode) return;
    _lastCode = raw;
    _cooldown?.cancel();
    _cooldown = Timer(const Duration(seconds: 2), () => _lastCode = null);

    _lookup(raw);
  }

  Future<void> _lookup(String code) async {
    FocusScope.of(context).unfocus();
    await ref.read(shipperScanControllerProvider.notifier).lookup(code);
  }

  void _submitManual() {
    final code = _manualCtrl.text.trim();
    if (code.isEmpty) return;
    _manualCtrl.clear();
    _lookup(code);
  }

  Future<void> _toggleTorch() async {
    await _scanner.toggleTorch();
    setState(() => _torchOn = !_torchOn);
  }

  Future<void> _onReceive() async {
    final ok = await ref
        .read(shipperScanControllerProvider.notifier)
        .receiveCurrent();
    // Nhận hàng xong → làm mới danh sách pickup để trạng thái đồng bộ.
    if (ok) ref.invalidate(pickupListControllerProvider);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(shipperScanControllerProvider);
    final theme = Theme.of(context);

    ref.listen(shipperScanControllerProvider, (prev, next) {
      final err = next.errorMessage;
      if (err != null && err != prev?.errorMessage) {
        _showSnack(err, isError: true);
        ref.read(shipperScanControllerProvider.notifier).clearMessages();
      }
      final ok = next.receivedMessage;
      if (ok != null && ok != prev?.receivedMessage) {
        _showSnack(ok);
        ref.read(shipperScanControllerProvider.notifier).clearMessages();
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Quét nhận hàng'),
        actions: [
          if (_cameraOn) ...[
            IconButton(
              icon: Icon(_torchOn ? Icons.flash_on : Icons.flash_off),
              tooltip: 'Đèn flash',
              onPressed: _toggleTorch,
            ),
            IconButton(
              icon: const Icon(Icons.cameraswitch_outlined),
              tooltip: 'Đổi camera',
              onPressed: () => _scanner.switchCamera(),
            ),
          ],
          if (state.recent.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.delete_outline),
              tooltip: 'Xoá lịch sử',
              onPressed: () => ref
                  .read(shipperScanControllerProvider.notifier)
                  .clearHistory(),
            ),
        ],
      ),
      body: AppPage(
        child: Column(
          children: [
            _buildScannerView(state, theme),
            _buildManualInput(state, theme),
            Expanded(
              child: state.result != null
                  ? SingleChildScrollView(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: ShipperScanResultCard(
                        result: state.result!,
                        isReceiving: state.isReceiving,
                        onReceive: _onReceive,
                        onClear: () => ref
                            .read(shipperScanControllerProvider.notifier)
                            .clearResult(),
                      ),
                    )
                  : _buildHistory(state, theme),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildScannerView(ShipperScanState state, ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 6, 14, 10),
      child: SizedBox(
        height: 292,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(18),
          child: _cameraOn
              ? _buildCameraStack(state, theme)
              : _buildCameraOff(theme),
        ),
      ),
    );
  }

  /// Khung hiển thị khi camera đang tắt: nút bật + gợi ý nhập tay.
  Widget _buildCameraOff(ThemeData theme) {
    return AppSurface(
      padding: EdgeInsets.zero,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: theme.colorScheme.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Icon(
                  Icons.photo_camera_outlined,
                  size: 34,
                  color: theme.colorScheme.primary,
                ),
              ),
              const SizedBox(height: 12),
              Text('Camera đang tắt', style: theme.textTheme.titleMedium),
              const SizedBox(height: 4),
              Text(
                'Bật camera để quét, hoặc nhập mã thủ công bên dưới.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: _toggleCamera,
                icon: const Icon(Icons.qr_code_scanner),
                label: const Text('Bật camera'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCameraStack(ShipperScanState state, ThemeData theme) {
    return Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(
          controller: _scanner,
          onDetect: _onDetect,
          errorBuilder: (context, error) => _CameraError(error: error),
        ),
        DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Colors.black.withValues(alpha: 0.2),
                Colors.transparent,
                Colors.black.withValues(alpha: 0.46),
              ],
            ),
          ),
        ),
        Center(
          child: Container(
            width: 230,
            height: 132,
            decoration: BoxDecoration(
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.95),
                width: 2,
              ),
              borderRadius: BorderRadius.circular(18),
            ),
          ),
        ),
        Positioned(
          left: 14,
          right: 14,
          top: 14,
          child: Align(
            alignment: Alignment.topRight,
            child: _CameraOffButton(onPressed: _toggleCamera),
          ),
        ),
        Positioned(
          left: 14,
          right: 14,
          bottom: 14,
          child: Row(
            children: [
              Expanded(
                child: Text(
                  'Đưa mã kiện vào khung để nhận hàng',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.22),
                  ),
                ),
                child: const Text(
                  'LIVE',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
        ),
        if (state.isLooking)
          Container(
            color: Colors.black.withValues(alpha: 0.35),
            child: const Center(child: CircularProgressIndicator()),
          ),
      ],
    );
  }

  Widget _buildManualInput(ShipperScanState state, ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
      child: AppSurface(
        padding: const EdgeInsets.all(10),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: _manualCtrl,
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => _submitManual(),
                decoration: const InputDecoration(
                  hintText: 'Nhập mã kiện thủ công',
                  prefixIcon: Icon(Icons.keyboard_outlined),
                ),
              ),
            ),
            const SizedBox(width: 8),
            FilledButton(
              onPressed: state.isBusy ? null : _submitManual,
              child: const Text('Quét'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHistory(ShipperScanState state, ThemeData theme) {
    if (state.recent.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: theme.colorScheme.primary.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  Icons.qr_code_scanner,
                  size: 30,
                  color: theme.colorScheme.primary,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                'Sẵn sàng quét mã kiện',
                textAlign: TextAlign.center,
                style: theme.textTheme.titleMedium,
              ),
              const SizedBox(height: 4),
              Text(
                'Camera sẽ tự tìm pickup khi phát hiện mã hợp lệ.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
      itemCount: state.recent.length,
      separatorBuilder: (_, _) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final scan = state.recent[index];
        final time =
            '${scan.scannedAt.hour.toString().padLeft(2, '0')}:${scan.scannedAt.minute.toString().padLeft(2, '0')}';
        return ListTile(
          dense: true,
          leading: Icon(
            scan.received ? Icons.check_circle : Icons.cancel_outlined,
            color: scan.received
                ? Colors.green
                : theme.colorScheme.onSurfaceVariant,
          ),
          title: Text(
            scan.code,
            style: const TextStyle(
              fontFamily: 'monospace',
              fontWeight: FontWeight.w700,
            ),
          ),
          subtitle: Text(
            scan.received
                ? 'Đã nhận · ${scan.pickupCode ?? ''} ${scan.statusLabel ?? ''}'
                : (scan.note ?? 'Không nhận được'),
          ),
          trailing: Text(time, style: theme.textTheme.bodySmall),
        );
      },
    );
  }

  void _showSnack(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context)
      ..clearSnackBars()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: isError ? Theme.of(context).colorScheme.error : null,
        ),
      );
  }
}

/// Nút nhỏ nổi trên khung camera để tắt nhanh, chuyển sang nhập tay.
class _CameraOffButton extends StatelessWidget {
  const _CameraOffButton({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.black.withValues(alpha: 0.4),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: onPressed,
        child: const Padding(
          padding: EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.videocam_off_outlined, color: Colors.white, size: 16),
              SizedBox(width: 6),
              Text(
                'Tắt camera',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CameraError extends StatelessWidget {
  const _CameraError({required this.error});

  final MobileScannerException error;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black87,
      padding: const EdgeInsets.all(24),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.no_photography_outlined,
              color: Colors.white70,
              size: 40,
            ),
            const SizedBox(height: 12),
            Text(
              _message(error),
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 8),
            const Text(
              'Bạn vẫn có thể nhập mã thủ công bên dưới.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white54, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  String _message(MobileScannerException e) {
    switch (e.errorCode) {
      case MobileScannerErrorCode.permissionDenied:
        return 'Chưa cấp quyền camera. Vào Cài đặt để bật quyền.';
      case MobileScannerErrorCode.unsupported:
        return 'Thiết bị không hỗ trợ camera quét.';
      default:
        return 'Không mở được camera.';
    }
  }
}
