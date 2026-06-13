import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

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
    formats: const [BarcodeFormat.qrCode, BarcodeFormat.code128],
    detectionSpeed: DetectionSpeed.normal,
  );
  final _manualCtrl = TextEditingController();

  String? _lastCode;
  Timer? _cooldown;
  bool _torchOn = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
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
    switch (state) {
      case AppLifecycleState.resumed:
        _scanner.start();
        break;
      case AppLifecycleState.inactive:
      case AppLifecycleState.paused:
      case AppLifecycleState.detached:
      case AppLifecycleState.hidden:
        _scanner.stop();
        break;
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
          if (state.recent.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.delete_outline),
              tooltip: 'Xoá lịch sử',
              onPressed: () =>
                  ref.read(shipperScanControllerProvider.notifier).clearHistory(),
            ),
        ],
      ),
      body: Column(
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
    );
  }

  Widget _buildScannerView(ShipperScanState state, ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 4, 12, 10),
      child: SizedBox(
        height: 280,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: Stack(
            fit: StackFit.expand,
            children: [
              MobileScanner(
                controller: _scanner,
                onDetect: _onDetect,
                errorBuilder: (context, error) =>
                    _CameraError(error: error),
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
                    borderRadius: BorderRadius.circular(8),
                  ),
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
          ),
        ),
      ),
    );
  }

  Widget _buildManualInput(ShipperScanState state, ThemeData theme) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: theme.colorScheme.surface,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: theme.colorScheme.outlineVariant),
        ),
        child: Padding(
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
