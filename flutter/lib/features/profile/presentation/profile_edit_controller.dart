import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../../auth/domain/auth_repository.dart';
import '../../auth/presentation/auth_controller.dart';

/// State cho form chỉnh sửa hồ sơ. Lỗi validation map theo field
/// (key trùng tên field backend) để gắn `errorText` cho từng ô.
class ProfileEditState {
  const ProfileEditState({
    this.savingInfo = false,
    this.savingPassword = false,
    this.uploadingAvatar = false,
    this.infoErrors = const {},
    this.passwordErrors = const {},
    this.generalError,
  });

  final bool savingInfo;
  final bool savingPassword;
  final bool uploadingAvatar;
  final Map<String, String> infoErrors;
  final Map<String, String> passwordErrors;
  final String? generalError;

  ProfileEditState copyWith({
    bool? savingInfo,
    bool? savingPassword,
    bool? uploadingAvatar,
    Map<String, String>? infoErrors,
    Map<String, String>? passwordErrors,
    String? generalError,
    bool clearInfoErrors = false,
    bool clearPasswordErrors = false,
    bool clearGeneral = false,
  }) {
    return ProfileEditState(
      savingInfo: savingInfo ?? this.savingInfo,
      savingPassword: savingPassword ?? this.savingPassword,
      uploadingAvatar: uploadingAvatar ?? this.uploadingAvatar,
      infoErrors: clearInfoErrors ? const {} : (infoErrors ?? this.infoErrors),
      passwordErrors: clearPasswordErrors
          ? const {}
          : (passwordErrors ?? this.passwordErrors),
      generalError: clearGeneral ? null : (generalError ?? this.generalError),
    );
  }
}

/// Quản lý cập nhật hồ sơ: thông tin, mật khẩu, avatar.
/// Mỗi action trả `bool` (true = thành công) để widget hiện toast / clear ô.
class ProfileEditController extends Notifier<ProfileEditState> {
  AuthRepository get _repo => ref.read(authRepositoryProvider);

  @override
  ProfileEditState build() => const ProfileEditState();

  Future<bool> saveInfo({
    required String fullname,
    String? email,
    String? phone,
    String? address,
  }) async {
    state = state.copyWith(
      savingInfo: true,
      clearInfoErrors: true,
      clearGeneral: true,
    );
    try {
      final session = await _repo.updateProfile(
        fullname: fullname,
        email: email,
        phone: phone,
        address: address,
      );
      ref.read(authControllerProvider.notifier).refreshSession(session);
      state = state.copyWith(savingInfo: false);
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(
        savingInfo: false,
        infoErrors: _fieldErrors(e),
        generalError: _general(e),
      );
      return false;
    }
  }

  Future<bool> savePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    state = state.copyWith(
      savingPassword: true,
      clearPasswordErrors: true,
      clearGeneral: true,
    );
    try {
      await _repo.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        confirmPassword: confirmPassword,
      );
      state = state.copyWith(savingPassword: false, clearPasswordErrors: true);
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(
        savingPassword: false,
        passwordErrors: _fieldErrors(e),
        generalError: _general(e),
      );
      return false;
    }
  }

  Future<bool> changeAvatar(String filePath) async {
    state = state.copyWith(uploadingAvatar: true, clearGeneral: true);
    try {
      final session = await _repo.updateAvatar(filePath);
      ref.read(authControllerProvider.notifier).refreshSession(session);
      state = state.copyWith(uploadingAvatar: false);
      return true;
    } on ApiException catch (e) {
      state = state.copyWith(uploadingAvatar: false, generalError: e.message);
      return false;
    }
  }

  /// Xóa lỗi/lưu trạng thái khi rời màn hoặc đổi tab.
  void clearErrors() {
    state = state.copyWith(
      clearInfoErrors: true,
      clearPasswordErrors: true,
      clearGeneral: true,
    );
  }

  /// Map `errors` (field -> [messages]) sang field -> message đầu.
  Map<String, String> _fieldErrors(ApiException e) {
    final errors = e.errors;
    if (errors == null) return const {};
    return errors.map(
      (key, value) => MapEntry(key, value.isEmpty ? '' : value.first),
    );
  }

  /// Lỗi chung (network/server) hiện qua toast; lỗi 422 đã map theo field.
  String? _general(ApiException e) =>
      e.kind == ApiErrorKind.validation ? null : e.message;
}

final profileEditControllerProvider =
    NotifierProvider<ProfileEditController, ProfileEditState>(
      ProfileEditController.new,
    );
