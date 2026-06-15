import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/device/device_name_provider.dart';
import '../../../core/providers.dart';
import '../../../shared/widgets/app_toast.dart';
import 'auth_controller.dart';

/// Màn đăng nhập (contract §2.1). Username + password, gửi kèm device_name.
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _usernameCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  late Future<_BiometricLoginState> _biometricLoginState;
  bool _obscure = true;

  @override
  void initState() {
    super.initState();
    _biometricLoginState = _loadBiometricLoginState();
  }

  @override
  void dispose() {
    _usernameCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;

    final deviceName = await ref.read(deviceNameProvider.future);
    await ref
        .read(authControllerProvider.notifier)
        .login(
          username: _usernameCtrl.text.trim(),
          password: _passwordCtrl.text,
          deviceName: deviceName,
        );
  }

  Future<_BiometricLoginState> _loadBiometricLoginState() async {
    final token = await ref.read(tokenStorageProvider).read();
    if (token == null || token.isEmpty) {
      return const _BiometricLoginState.unavailable();
    }

    final availability = await ref.read(biometricAuthProvider).availability();
    return _BiometricLoginState(
      available: availability.canAuthenticate,
      label: availability.label,
      iconName: availability.iconName,
    );
  }

  Future<void> _unlockWithBiometrics() async {
    await ref.read(authControllerProvider.notifier).unlockWithBiometrics();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(authControllerProvider);

    // Hiện lỗi login dạng toast.
    ref.listen(authControllerProvider, (prev, next) {
      final msg = next.errorMessage;
      if (msg != null && msg != prev?.errorMessage) {
        AppToast.error(context, msg);
      }
    });

    return Scaffold(
      body: _LoginBackground(
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              return Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(22, 16, 22, 22),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 390),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        SizedBox(height: constraints.maxHeight < 760 ? 8 : 30),
                        const _BrandHeader(),
                        const SizedBox(height: 18),
                        FutureBuilder<_BiometricLoginState>(
                          future: _biometricLoginState,
                          builder: (context, snapshot) {
                            return _LoginPanel(
                              formKey: _formKey,
                              usernameCtrl: _usernameCtrl,
                              passwordCtrl: _passwordCtrl,
                              obscure: _obscure,
                              submitting: state.isSubmitting,
                              biometric:
                                  snapshot.data ??
                                  const _BiometricLoginState.unavailable(),
                              onTogglePassword: () =>
                                  setState(() => _obscure = !_obscure),
                              onSubmit: _submit,
                              onBiometricLogin: _unlockWithBiometrics,
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _BiometricLoginState {
  const _BiometricLoginState({
    required this.available,
    required this.label,
    required this.iconName,
  });

  const _BiometricLoginState.unavailable()
    : available = false,
      label = 'Sinh trắc học',
      iconName = 'fingerprint';

  final bool available;
  final String label;
  final String iconName;
}

class _LoginBackground extends StatelessWidget {
  const _LoginBackground({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFE6F0FF), Color(0xFFF8FBFF), Color(0xFFDCE8FF)],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            top: -110,
            left: -90,
            child: _BlurOrb(
              size: 230,
              color: const Color(0xFF2563EB).withValues(alpha: 0.13),
            ),
          ),
          Positioned(
            right: -120,
            top: 160,
            child: _BlurOrb(
              size: 250,
              color: const Color(0xFF2563EB).withValues(alpha: 0.1),
            ),
          ),
          Positioned(
            left: 28,
            right: 28,
            bottom: -70,
            child: Container(
              height: 190,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.22),
                borderRadius: BorderRadius.circular(36),
              ),
            ),
          ),
          Positioned.fill(child: child),
        ],
      ),
    );
  }
}

class _BlurOrb extends StatelessWidget {
  const _BlurOrb({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: color,
        shape: BoxShape.circle,
        boxShadow: [BoxShadow(color: color, blurRadius: 70, spreadRadius: 18)],
      ),
    );
  }
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      children: [
        Container(
          width: 66,
          height: 66,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF2563EB), Color(0xFF1D4ED8)],
            ),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: Colors.white.withValues(alpha: 0.72)),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF2563EB).withValues(alpha: 0.18),
                blurRadius: 22,
                offset: const Offset(0, 12),
              ),
            ],
          ),
          child: const Center(
            child: Text(
              'H',
              style: TextStyle(
                color: Colors.white,
                fontSize: 36,
                fontWeight: FontWeight.w900,
                height: 1,
              ),
            ),
          ),
        ),
        const SizedBox(height: 14),
        Text(
          'Bee Express',
          textAlign: TextAlign.center,
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w900,
            letterSpacing: 0.4,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          'Hệ thống quản lý vận chuyển',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 10),
        const Wrap(
          alignment: WrapAlignment.center,
          spacing: 8,
          runSpacing: 8,
          children: [
            _ModuleBadge(icon: Icons.local_shipping_outlined, label: 'Shipper'),
            _ModuleBadge(icon: Icons.qr_code_scanner_rounded, label: 'OPS'),
          ],
        ),
      ],
    );
  }
}

class _ModuleBadge extends StatelessWidget {
  const _ModuleBadge({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.68),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.8)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: theme.colorScheme.primary),
          const SizedBox(width: 5),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurface,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _LoginPanel extends StatelessWidget {
  const _LoginPanel({
    required this.formKey,
    required this.usernameCtrl,
    required this.passwordCtrl,
    required this.obscure,
    required this.submitting,
    required this.biometric,
    required this.onTogglePassword,
    required this.onSubmit,
    required this.onBiometricLogin,
  });

  final GlobalKey<FormState> formKey;
  final TextEditingController usernameCtrl;
  final TextEditingController passwordCtrl;
  final bool obscure;
  final bool submitting;
  final _BiometricLoginState biometric;
  final VoidCallback onTogglePassword;
  final VoidCallback onSubmit;
  final VoidCallback onBiometricLogin;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.9),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withValues(alpha: 0.9)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: 0.08),
            blurRadius: 24,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
        child: Form(
          key: formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Đăng nhập',
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          'Truy cập vào hệ thống vận hành.',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.primary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      'SECURE',
                      style: TextStyle(
                        color: theme.colorScheme.primary,
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.6,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              _LoginField(
                controller: usernameCtrl,
                label: 'Tên đăng nhập',
                icon: Icons.person_outline,
                textInputAction: TextInputAction.next,
                validator: (v) => (v == null || v.trim().isEmpty)
                    ? 'Vui lòng nhập tên đăng nhập.'
                    : null,
              ),
              const SizedBox(height: 12),
              _LoginField(
                controller: passwordCtrl,
                label: 'Mật khẩu',
                icon: Icons.lock_outline,
                obscureText: obscure,
                textInputAction: TextInputAction.done,
                onSubmitted: (_) => onSubmit(),
                suffixIcon: IconButton(
                  icon: Icon(
                    obscure
                        ? Icons.visibility_off_outlined
                        : Icons.visibility_outlined,
                  ),
                  onPressed: onTogglePassword,
                ),
                validator: (v) =>
                    (v == null || v.isEmpty) ? 'Vui lòng nhập mật khẩu.' : null,
              ),
              const SizedBox(height: 16),
              SizedBox(
                height: 48,
                child: FilledButton(
                  onPressed: submitting ? null : onSubmit,
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFF2563EB),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    elevation: 0,
                  ),
                  child: submitting
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.arrow_forward_rounded),
                            SizedBox(width: 10),
                            Text(
                              'Đăng nhập',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ],
                        ),
                ),
              ),
              if (biometric.available) ...[
                const SizedBox(height: 10),
                SizedBox(
                  height: 46,
                  child: OutlinedButton.icon(
                    onPressed: submitting ? null : onBiometricLogin,
                    icon: Icon(
                      biometric.iconName == 'face'
                          ? Icons.face_retouching_natural_outlined
                          : Icons.fingerprint_rounded,
                    ),
                    label: Text(
                      'Đăng nhập bằng ${biometric.label}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: theme.colorScheme.primary,
                      side: BorderSide(
                        color: theme.colorScheme.primary.withValues(
                          alpha: 0.28,
                        ),
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _LoginField extends StatelessWidget {
  const _LoginField({
    required this.controller,
    required this.label,
    required this.icon,
    this.obscureText = false,
    this.textInputAction,
    this.onSubmitted,
    this.suffixIcon,
    this.validator,
  });

  final TextEditingController controller;
  final String label;
  final IconData icon;
  final bool obscureText;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onSubmitted;
  final Widget? suffixIcon;
  final FormFieldValidator<String>? validator;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return TextFormField(
      controller: controller,
      obscureText: obscureText,
      autocorrect: false,
      enableSuggestions: !obscureText,
      textInputAction: textInputAction,
      onFieldSubmitted: onSubmitted,
      validator: validator,
      decoration: InputDecoration(
        labelText: label,
        isDense: true,
        prefixIcon: Icon(icon, size: 21),
        prefixIconConstraints: const BoxConstraints(
          minWidth: 46,
          minHeight: 46,
        ),
        suffixIcon: suffixIcon,
        suffixIconConstraints: const BoxConstraints(
          minWidth: 46,
          minHeight: 46,
        ),
        filled: true,
        fillColor: const Color(0xFFF8FAFC),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 14,
        ),
        labelStyle: TextStyle(color: theme.colorScheme.onSurfaceVariant),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: theme.colorScheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: theme.colorScheme.primary, width: 1.4),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: theme.colorScheme.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: theme.colorScheme.error, width: 1.4),
        ),
      ),
    );
  }
}
