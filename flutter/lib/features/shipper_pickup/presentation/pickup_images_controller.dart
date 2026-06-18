import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_exception.dart';
import '../domain/pickup_image.dart';
import '../domain/pickup_repository.dart';
import 'pickup_providers.dart';

/// State danh sách ảnh bằng chứng của một pickup.
class PickupImagesState {
  const PickupImagesState({
    this.images = const [],
    this.isLoading = true,
    this.isUploading = false,
    this.deletingId,
    this.errorMessage,
  });

  final List<PickupImage> images;
  final bool isLoading;
  final bool isUploading;

  /// Id ảnh đang bị xóa (để UI hiện spinner trên đúng ảnh).
  final int? deletingId;
  final String? errorMessage;

  PickupImagesState copyWith({
    List<PickupImage>? images,
    bool? isLoading,
    bool? isUploading,
    int? deletingId,
    bool clearDeleting = false,
    String? errorMessage,
    bool clearError = false,
  }) {
    return PickupImagesState(
      images: images ?? this.images,
      isLoading: isLoading ?? this.isLoading,
      isUploading: isUploading ?? this.isUploading,
      deletingId: clearDeleting ? null : (deletingId ?? this.deletingId),
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
    );
  }
}

/// Quản lý ảnh bằng chứng theo pickupId (family).
class PickupImagesController
    extends FamilyNotifier<PickupImagesState, int> {
  PickupRepository get _repo => ref.read(pickupRepositoryProvider);

  late int _pickupId;

  @override
  PickupImagesState build(int arg) {
    _pickupId = arg;
    Future.microtask(load);
    return const PickupImagesState(isLoading: true);
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final images = await _repo.listImages(_pickupId);
      state = state.copyWith(images: images, isLoading: false);
    } on Object catch (e) {
      state = state.copyWith(isLoading: false, errorMessage: _messageOf(e));
    }
  }

  /// Upload một ảnh; chèn vào đầu danh sách khi xong. Trả `true` nếu thành công.
  Future<bool> upload(String filePath) async {
    state = state.copyWith(isUploading: true, clearError: true);
    try {
      final image = await _repo.uploadImage(_pickupId, filePath);
      state = state.copyWith(
        images: [image, ...state.images],
        isUploading: false,
      );
      return true;
    } on Object catch (e) {
      state = state.copyWith(isUploading: false, errorMessage: _messageOf(e));
      return false;
    }
  }

  /// Xóa một ảnh; bỏ khỏi danh sách khi xong. Trả `true` nếu thành công.
  Future<bool> delete(int imageId) async {
    state = state.copyWith(deletingId: imageId, clearError: true);
    try {
      await _repo.deleteImage(_pickupId, imageId);
      state = state.copyWith(
        images: state.images.where((img) => img.id != imageId).toList(),
        clearDeleting: true,
      );
      return true;
    } on Object catch (e) {
      state = state.copyWith(clearDeleting: true, errorMessage: _messageOf(e));
      return false;
    }
  }

  static String _messageOf(Object e) {
    if (e is ApiException && e.message.isNotEmpty) return e.message;
    return 'Thao tác thất bại. Vui lòng thử lại.';
  }
}

final pickupImagesControllerProvider = NotifierProvider.family<
    PickupImagesController, PickupImagesState, int>(
  PickupImagesController.new,
);
