import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import 'scan_controller.dart';
import 'widgets/scan_result_card.dart';

/// Màn quét OPS (contract §4). Camera quét QR/Code128 + nhập tay fallback.
///
/// Mỗi mã quét gọi /ops/scan (chỉ đọc) rồi hiện kết quả. Nếu đơn cho phép thì
/// có nút xác nhận nhập kho (/receive). Lịch sử quét lưu local trong phiên.
class OpsScannerScreen extends ConsumerStatefulWidget {
  const OpsScannerScreen({super.key});

  @override
  ConsumerState<OpsScannerScreen> createState() => _OpsScannerScreenState();
}

class _OpsScannerScreenState extends ConsumerState<OpsScannerScreen>
    with WidgetsBindingObserver {
  final MobileScannerController _scanner = MobileScannerController(
    formats: const [BarcodeFormat.qrCode, BarcodeFormat.code128],
    detectionSpeed: DetectionSpeed.normal,
  );
  final _manualCtrl = TextEditingController();

  /// Chống quét trùng liên tục: chặn cùng mã trong khoảng ngắn.
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
    // Tạm dừng camera khi app nền để tiết kiệm pin / tránh leak.
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
    if (ref.read(scanControllerProvider).isBusy) return;
    final raw = capture.barcodes
        .map((b) => b.rawValue)
        .firstWhere((v) => v != null && v.isNotEmpty, orElse: () => null);
    if (raw == null) return;

    // Bỏ qua nếu trùng mã vừa quét (trong cooldown).
    if (raw == _lastCode) return;
    _lastCode = raw;
    _cooldown?.cancel();
    _cooldown = Timer(const Duration(seconds: 2), () => _lastCode = null);

    _lookup(raw);
  }

  Future<void> _lookup(String code) async {
    FocusScope.of(context).unfocus();
    await ref.read(scanControllerProvider.notifier).lookup(code);
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

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(scanControllerProvider);
    final theme = Theme.of(context);

    // SnackBar cho lỗi / nhập kho thành công.
    ref.listen(scanControllerProvider, (prev, next) {
      final err = next.errorMessage;
      if (err != null && err != prev?.errorMessage) {
        _showSnack(err, isError: true);
      }
      final ok = next.receivedMessage;
      if (ok != null && ok != prev?.receivedMessage) {
        _showSnack(ok);
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Quét nhập kho'),
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
          IconButton(
            icon: const Icon(Icons.history),
            tooltip: 'Lịch sử quét',
            onPressed: () => context.push('/ops/recent'),
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
                    child: ScanResultCard(
                      result: state.result!,
                      isReceiving: state.isReceiving,
                      onReceive: () => ref
                          .read(scanControllerProvider.notifier)
                          .receiveCurrent(),
                      onClear: () => ref
                          .read(scanControllerProvider.notifier)
                          .clearResult(),
                    ),
                  )
                : _buildHint(state, theme),
          ),
        ],
      ),
    );
  }

  Widget _buildScannerView(ScanState state, ThemeData theme) {
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
                        'Đưa mã vận đơn vào khung để tra cứu',
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

  Widget _buildManualInput(ScanState state, ThemeData theme) {
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
                    hintText: 'Nhập mã thủ công',
                    prefixIcon: Icon(Icons.keyboard_outlined),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              FilledButton(
                onPressed: state.isBusy ? null : _submitManual,
                child: const Text('Tra cứu'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHint(ScanState state, ThemeData theme) {
    final lastReceived = state.recent.where((e) => e.received).length;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: theme.colorScheme.outlineVariant),
          ),
          child: Padding(
            padding: const EdgeInsets.all(22),
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
                  'Sẵn sàng quét mã vận đơn',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.titleMedium,
                ),
                const SizedBox(height: 4),
                Text(
                  'Camera sẽ tự tra cứu khi phát hiện mã hợp lệ.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                if (state.recent.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  TextButton.icon(
                    onPressed: () => context.push('/ops/recent'),
                    icon: const Icon(Icons.history, size: 18),
                    label: Text('Phiên này: đã nhập $lastReceived đơn'),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
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
