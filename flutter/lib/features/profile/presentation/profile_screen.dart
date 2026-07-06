import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/env.dart';
import '../../../shared/widgets/app_surfaces.dart';
import '../../../shared/widgets/app_toast.dart';
import '../../auth/presentation/auth_controller.dart';
import 'profile_edit_controller.dart';

/// Màn Tài khoản: xem + cập nhật thông tin cá nhân, đổi mật khẩu, đổi avatar.
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController = TabController(
    length: 2,
    vsync: this,
  );

  // Tab Thông tin.
  final _fullnameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();

  // Tab Đổi mật khẩu.
  final _currentPwCtrl = TextEditingController();
  final _newPwCtrl = TextEditingController();
  final _confirmPwCtrl = TextEditingController();

  bool _obscureCurrent = true;
  bool _obscureNew = true;
  bool _obscureConfirm = true;

  final _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    // Prefill 1 lần từ session hiện tại (không rebuild khi refreshSession).
    final user = ref.read(authControllerProvider).session?.user;
    _fullnameCtrl.text = user?.fullname ?? '';
    _emailCtrl.text = user?.email ?? '';
    _phoneCtrl.text = user?.phone ?? '';
    _addressCtrl.text = user?.address ?? '';
    // Cập nhật checklist mật khẩu khi gõ.
    _newPwCtrl.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _tabController.dispose();
    _fullnameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _addressCtrl.dispose();
    _currentPwCtrl.dispose();
    _newPwCtrl.dispose();
    _confirmPwCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final session = ref.watch(authControllerProvider).session;
    final user = session?.user;
    // Chừa đáy = safe area (home indicator) + chiều cao bottom nav + FAB nhô lên,
    // vì shell OPS dùng extendBody nên body vẽ xuống dưới thanh nav.
    final bottomInset = MediaQuery.of(context).padding.bottom + 96;

    // Lỗi chung (network/server) → toast. So sánh cả state để lỗi giống nhau
    // liên tiếp vẫn hiện lại.
    ref.listen(profileEditControllerProvider, (prev, next) {
      if (next.generalError != null && prev != next) {
        AppToast.error(context, next.generalError!);
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tài khoản'),
        bottom: TabBar(
          controller: _tabController,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          tabs: const [
            Tab(text: 'Thông tin'),
            Tab(text: 'Đổi mật khẩu'),
          ],
        ),
      ),
      body: AppPage(
        child: TabBarView(
          controller: _tabController,
          children: [
            _buildInfoTab(theme, user, session?.roles ?? const [], bottomInset),
            _buildPasswordTab(theme, bottomInset),
          ],
        ),
      ),
    );
  }

  // ─── Avatar ───

  Future<void> _pickAvatar(ImageSource source) async {
    try {
      final file = await _picker.pickImage(
        source: source,
        maxWidth: 1024,
        imageQuality: 85,
      );
      if (file == null || !mounted) return;
      final ok = await ref
          .read(profileEditControllerProvider.notifier)
          .changeAvatar(file.path);
      if (ok && mounted) {
        AppToast.success(context, 'Cập nhật ảnh đại diện thành công.');
      }
    } catch (_) {
      if (mounted) {
        AppToast.error(context, 'Không mở được ảnh. Vui lòng thử lại.');
      }
    }
  }

  void _openAvatarSheet() {
    showModalBottomSheet<void>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Chọn từ thư viện'),
              onTap: () {
                Navigator.pop(sheetContext);
                _pickAvatar(ImageSource.gallery);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Chụp ảnh mới'),
              onTap: () {
                Navigator.pop(sheetContext);
                _pickAvatar(ImageSource.camera);
              },
            ),
          ],
        ),
      ),
    );
  }

  /// Resolve avatar về URL đầy đủ. Backend thường đã trả full URL; phòng
  /// session cũ còn lưu đường dẫn tương đối `/uploads/...` thì ghép host gốc.
  String? _resolveAvatar(String? raw) {
    final v = raw?.trim();
    if (v == null || v.isEmpty) return null;
    if (v.startsWith('http://') || v.startsWith('https://')) return v;
    return '${Env.apiBaseUrl}$v';
  }

  Widget _buildAvatarHeader(ThemeData theme, dynamic user) {
    final editState = ref.watch(profileEditControllerProvider);
    final avatarUrl = _resolveAvatar(user?.avatar as String?);
    final username = (user?.username as String?)?.trim() ?? '';
    final fullname = (user?.fullname as String?)?.trim() ?? '';
    final initial = username.isNotEmpty ? username[0].toUpperCase() : 'U';
    final fallback = Text(
      initial,
      style: const TextStyle(
        fontSize: 26,
        color: Colors.white,
        fontWeight: FontWeight.bold,
      ),
    );

    return AppHeroPanel(
      trailingIcon: Icons.account_circle_outlined,
      child: Padding(
        padding: EdgeInsets.zero,
        child: Row(
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: 68,
                  height: 68,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.16),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: avatarUrl == null
                      ? Center(child: fallback)
                      : Image.network(
                          avatarUrl,
                          fit: BoxFit.cover,
                          // Đổi ảnh → URL mới (filename theo time) → tự load lại.
                          loadingBuilder: (context, child, progress) {
                            if (progress == null) return child;
                            return const Center(
                              child: SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              ),
                            );
                          },
                          errorBuilder: (context, _, _) =>
                              Center(child: fallback),
                        ),
                ),
                Positioned(
                  right: -2,
                  bottom: -2,
                  child: Material(
                    color: Colors.white,
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: editState.uploadingAvatar
                          ? null
                          : _openAvatarSheet,
                      child: Padding(
                        padding: const EdgeInsets.all(6),
                        child: editState.uploadingAvatar
                            ? const SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Icon(
                                Icons.photo_camera,
                                size: 16,
                                color: theme.colorScheme.primary,
                              ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    (user?.fullname as String?)?.isNotEmpty == true
                        ? fullname
                        : ((user?.username as String?) ?? '—'),
                    style: theme.textTheme.titleLarge?.copyWith(
                      color: Colors.white,
                    ),
                  ),
                  if (username.isNotEmpty)
                    Text(
                      '@$username',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: Colors.white.withValues(alpha: 0.74),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ─── Tab Thông tin ───

  Widget _buildInfoTab(
    ThemeData theme,
    dynamic user,
    List<String> roles,
    double bottomInset,
  ) {
    final state = ref.watch(profileEditControllerProvider);

    return ListView(
      padding: EdgeInsets.fromLTRB(16, 16, 16, bottomInset),
      children: [
        _buildAvatarHeader(theme, user),
        const SizedBox(height: 16),
        AppSurface(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 18),
          child: Column(
            children: [
              Row(
                children: [
                  Expanded(
                    child: _readonlyField(
                      theme,
                      label: 'Tên đăng nhập',
                      value: (user?.username as String?) ?? '—',
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _readonlyField(
                      theme,
                      label: 'Mã nhân viên',
                      value: ((user?.code as String?)?.isNotEmpty ?? false)
                          ? user!.code as String
                          : '—',
                    ),
                  ),
                ],
              ),
              if (roles.isNotEmpty) ...[
                const SizedBox(height: 14),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final role in roles)
                        Chip(
                          avatar: const Icon(Icons.verified_user_outlined),
                          label: Text(role),
                        ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 18),
              _fieldLabel(theme, 'Họ và tên'),
              TextField(
                controller: _fullnameCtrl,
                textCapitalization: TextCapitalization.words,
                decoration: InputDecoration(
                  hintText: 'Nhập họ và tên',
                  prefixIcon: const Icon(Icons.person_outline),
                  errorText: state.infoErrors['fullname'],
                ),
              ),
              const SizedBox(height: 14),
              _fieldLabel(theme, 'Email'),
              TextField(
                controller: _emailCtrl,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(
                  hintText: 'Nhập email',
                  prefixIcon: const Icon(Icons.email_outlined),
                  errorText: state.infoErrors['email'],
                ),
              ),
              const SizedBox(height: 14),
              _fieldLabel(theme, 'Số điện thoại'),
              TextField(
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(
                  hintText: 'Nhập số điện thoại',
                  prefixIcon: const Icon(Icons.phone_outlined),
                  errorText: state.infoErrors['phone'],
                ),
              ),
              const SizedBox(height: 14),
              _fieldLabel(theme, 'Địa chỉ'),
              TextField(
                controller: _addressCtrl,
                decoration: InputDecoration(
                  hintText: 'Nhập địa chỉ',
                  prefixIcon: const Icon(Icons.location_on_outlined),
                  errorText: state.infoErrors['address'],
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: state.savingInfo ? null : _onSaveInfo,
                  icon: state.savingInfo
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_outlined),
                  label: const Text('Lưu thay đổi'),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        AppSurface(
          padding: const EdgeInsets.all(12),
          child: SizedBox(
            width: double.infinity,
            child: FilledButton.tonalIcon(
              onPressed: () =>
                  ref.read(authControllerProvider.notifier).logout(),
              icon: const Icon(Icons.logout),
              label: const Text('Đăng xuất'),
            ),
          ),
        ),
      ],
    );
  }

  /// Label nằm phía trên field (tránh label nổi đè prefix icon).
  Widget _fieldLabel(ThemeData theme, String text) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 6),
      child: Text(
        text,
        style: theme.textTheme.labelMedium?.copyWith(
          fontWeight: FontWeight.w600,
          color: theme.colorScheme.onSurfaceVariant,
        ),
      ),
    );
  }

  Future<void> _onSaveInfo() async {
    FocusScope.of(context).unfocus();
    final ok = await ref
        .read(profileEditControllerProvider.notifier)
        .saveInfo(
          fullname: _fullnameCtrl.text.trim(),
          email: _emailCtrl.text.trim(),
          phone: _phoneCtrl.text.trim(),
          address: _addressCtrl.text.trim(),
        );
    if (ok && mounted) {
      AppToast.success(context, 'Cập nhật thông tin thành công.');
    }
  }

  // ─── Tab Đổi mật khẩu ───

  Widget _buildPasswordTab(ThemeData theme, double bottomInset) {
    final state = ref.watch(profileEditControllerProvider);
    final pw = _newPwCtrl.text;

    return ListView(
      padding: EdgeInsets.fromLTRB(16, 20, 16, bottomInset),
      children: [
        AppSurface(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 18),
          child: Column(
            children: [
              _fieldLabel(theme, 'Mật khẩu cũ'),
              TextField(
                controller: _currentPwCtrl,
                obscureText: _obscureCurrent,
                decoration: InputDecoration(
                  hintText: 'Nhập mật khẩu hiện tại',
                  prefixIcon: const Icon(Icons.lock_outline),
                  errorText: state.passwordErrors['current_password'],
                  suffixIcon: IconButton(
                    icon: Icon(
                      _obscureCurrent ? Icons.visibility_off : Icons.visibility,
                    ),
                    onPressed: () =>
                        setState(() => _obscureCurrent = !_obscureCurrent),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              _fieldLabel(theme, 'Mật khẩu mới'),
              TextField(
                controller: _newPwCtrl,
                obscureText: _obscureNew,
                decoration: InputDecoration(
                  hintText: 'Nhập mật khẩu mới',
                  prefixIcon: const Icon(Icons.lock_reset_outlined),
                  errorText: state.passwordErrors['new_password'],
                  suffixIcon: IconButton(
                    icon: Icon(
                      _obscureNew ? Icons.visibility_off : Icons.visibility,
                    ),
                    onPressed: () => setState(() => _obscureNew = !_obscureNew),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              _fieldLabel(theme, 'Nhập lại mật khẩu mới'),
              TextField(
                controller: _confirmPwCtrl,
                obscureText: _obscureConfirm,
                decoration: InputDecoration(
                  hintText: 'Nhập lại mật khẩu mới',
                  prefixIcon: const Icon(Icons.lock_outline),
                  errorText: state.passwordErrors['confirm_password'],
                  suffixIcon: IconButton(
                    icon: Icon(
                      _obscureConfirm ? Icons.visibility_off : Icons.visibility,
                    ),
                    onPressed: () =>
                        setState(() => _obscureConfirm = !_obscureConfirm),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              _buildPasswordChecklist(theme, pw),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: state.savingPassword ? null : _onSavePassword,
                  icon: state.savingPassword
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.check),
                  label: const Text('Đổi mật khẩu'),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _onSavePassword() async {
    FocusScope.of(context).unfocus();
    final ok = await ref
        .read(profileEditControllerProvider.notifier)
        .savePassword(
          currentPassword: _currentPwCtrl.text,
          newPassword: _newPwCtrl.text,
          confirmPassword: _confirmPwCtrl.text,
        );
    if (ok && mounted) {
      _currentPwCtrl.clear();
      _newPwCtrl.clear();
      _confirmPwCtrl.clear();
      AppToast.success(context, 'Đổi mật khẩu thành công.');
    }
  }

  Widget _buildPasswordChecklist(ThemeData theme, String pw) {
    final rules = <(String, bool)>[
      ('Tối thiểu 8 ký tự', pw.length >= 8),
      ('Ít nhất 1 chữ hoa', RegExp(r'[A-Z]').hasMatch(pw)),
      ('Ít nhất 1 số', RegExp(r'\d').hasMatch(pw)),
      (
        'Ít nhất 1 ký tự đặc biệt',
        RegExp(r'''[!@#$%^&*()_+\-=\[\]{};:'",.<>/?\\|`~]''').hasMatch(pw),
      ),
    ];

    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainer,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: theme.colorScheme.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Mật khẩu phải đáp ứng:',
              style: theme.textTheme.labelMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            ...rules.map((r) {
              final passed = r.$2;
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(
                  children: [
                    Icon(
                      passed
                          ? Icons.check_circle
                          : Icons.radio_button_unchecked,
                      size: 16,
                      color: passed ? Colors.green : theme.colorScheme.outline,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      r.$1,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: passed
                            ? Colors.green.shade700
                            : theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              );
            }),
          ],
        ),
      ),
    );
  }

  Widget _readonlyField(
    ThemeData theme, {
    required String label,
    required String value,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: theme.textTheme.labelSmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 4),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            color: theme.colorScheme.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodyMedium,
          ),
        ),
      ],
    );
  }
}
