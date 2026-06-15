import 'package:flutter/material.dart';

/// Loại toast quyết định màu sắc + icon.
enum AppToastType { success, error, warning, info }

/// Toast dùng chung cho toàn app.
///
/// Thay cho `SnackBar` mặc định: bo góc, có icon tròn, dải màu theo loại,
/// nền sáng tách khỏi nội dung và hiệu ứng nổi nhẹ. Hiển thị floating ở đáy
/// màn hình, trải đều hai bên.
///
/// Dùng nhanh:
/// ```dart
/// AppToast.error(context, 'Tài khoản hoặc mật khẩu không đúng.');
/// AppToast.success(context, 'Đã lưu thành công');
/// ```
abstract class AppToast {
  static void success(
    BuildContext context,
    String message, {
    String? title,
    Duration? duration,
  }) => show(
    context,
    message,
    type: AppToastType.success,
    title: title,
    duration: duration,
  );

  static void error(
    BuildContext context,
    String message, {
    String? title,
    Duration? duration,
  }) => show(
    context,
    message,
    type: AppToastType.error,
    title: title,
    duration: duration,
  );

  static void warning(
    BuildContext context,
    String message, {
    String? title,
    Duration? duration,
  }) => show(
    context,
    message,
    type: AppToastType.warning,
    title: title,
    duration: duration,
  );

  static void info(
    BuildContext context,
    String message, {
    String? title,
    Duration? duration,
  }) => show(
    context,
    message,
    type: AppToastType.info,
    title: title,
    duration: duration,
  );

  /// Hiển thị toast. Tự xóa toast đang hiện để tránh chồng nhiều cái.
  static void show(
    BuildContext context,
    String message, {
    AppToastType type = AppToastType.info,
    String? title,
    Duration? duration,
  }) {
    final messenger = ScaffoldMessenger.of(context);
    // Dùng property `width` của SnackBar (căn giữa, full ngang trừ 28px hai bên).
    // `width` và `margin` loại trừ nhau nên không set margin.
    final width = MediaQuery.sizeOf(context).width - 28;
    messenger
      ..clearSnackBars()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: Colors.transparent,
          elevation: 0,
          padding: EdgeInsets.zero,
          duration: duration ?? _defaultDuration(type),
          dismissDirection: DismissDirection.horizontal,
          width: width,
          content: _ToastBody(type: type, title: title, message: message),
        ),
      );
  }

  static Duration _defaultDuration(AppToastType type) {
    return type == AppToastType.error || type == AppToastType.warning
        ? const Duration(seconds: 5)
        : const Duration(seconds: 3);
  }
}

/// Cấu hình màu/icon theo từng loại toast.
class _ToastStyle {
  const _ToastStyle({
    required this.accent,
    required this.icon,
    required this.fallbackTitle,
  });

  final Color accent;
  final IconData icon;
  final String fallbackTitle;

  static _ToastStyle of(AppToastType type) {
    switch (type) {
      case AppToastType.success:
        return const _ToastStyle(
          accent: Color(0xFF16A34A),
          icon: Icons.check_circle_rounded,
          fallbackTitle: 'Thành công',
        );
      case AppToastType.error:
        return const _ToastStyle(
          accent: Color(0xFFDC2626),
          icon: Icons.error_rounded,
          fallbackTitle: 'Có lỗi xảy ra',
        );
      case AppToastType.warning:
        return const _ToastStyle(
          accent: Color(0xFFF59E0B),
          icon: Icons.warning_amber_rounded,
          fallbackTitle: 'Lưu ý',
        );
      case AppToastType.info:
        return const _ToastStyle(
          accent: Color(0xFF2563EB),
          icon: Icons.info_rounded,
          fallbackTitle: 'Thông báo',
        );
    }
  }
}

class _ToastBody extends StatelessWidget {
  const _ToastBody({
    required this.type,
    required this.title,
    required this.message,
  });

  final AppToastType type;
  final String? title;
  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = _ToastStyle.of(type);
    final resolvedTitle = title ?? style.fallbackTitle;

    return Material(
      color: Colors.transparent,
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: style.accent.withValues(alpha: 0.18)),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F172A).withValues(alpha: 0.12),
              blurRadius: 24,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Dải màu nhấn bên trái.
              Container(width: 5, color: style.accent),
              Padding(
                padding: const EdgeInsets.all(12),
                child: Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: style.accent.withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(style.icon, color: style.accent, size: 22),
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        resolvedTitle,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        message,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: const Color(0xFF475569),
                          height: 1.3,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              IconButton(
                onPressed: () =>
                    ScaffoldMessenger.of(context).hideCurrentSnackBar(),
                icon: const Icon(Icons.close_rounded, size: 18),
                color: const Color(0xFF94A3B8),
                visualDensity: VisualDensity.compact,
                tooltip: 'Đóng',
              ),
            ],
          ),
        ),
      ),
    );
  }
}
