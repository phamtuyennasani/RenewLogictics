/// Module mặc định backend tính theo role (contract §2.1).
enum DefaultModule {
  shipper,
  ops,
  chooser,
  unknown;

  static DefaultModule fromString(String? value) {
    switch (value) {
      case 'shipper':
        return DefaultModule.shipper;
      case 'ops':
        return DefaultModule.ops;
      case 'chooser':
        return DefaultModule.chooser;
      default:
        return DefaultModule.unknown;
    }
  }
}

/// Thông tin user đăng nhập (block `data.user` + roles/permissions/default_module).
class AuthUser {
  const AuthUser({
    required this.id,
    required this.username,
    required this.fullname,
    this.code,
    this.phone,
    this.email,
    this.address,
    this.avatar,
  });

  final int id;
  final String username;
  final String fullname;
  final String? code;
  final String? phone;
  final String? email;
  final String? address;
  final String? avatar;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: (json['id'] as num?)?.toInt() ?? 0,
      username: (json['username'] ?? '').toString(),
      fullname: (json['fullname'] ?? '').toString(),
      code: json['code']?.toString(),
      phone: json['phone']?.toString(),
      email: json['email']?.toString(),
      address: json['address']?.toString(),
      avatar: json['avatar']?.toString(),
    );
  }
}

/// Phiên đăng nhập: user + quyền + module điều hướng.
/// Token KHÔNG nằm ở đây — lưu riêng trong secure storage.
class UserSession {
  const UserSession({
    required this.user,
    required this.roles,
    required this.permissions,
    required this.defaultModule,
  });

  final AuthUser user;
  final List<String> roles;
  final List<String> permissions;
  final DefaultModule defaultModule;

  bool get isShipper => roles.contains('shipper');
  bool get isOpsCapable =>
      roles.any((r) => const ['ops', 'admin', 'manager', 'cs'].contains(r));

  /// App hiện chỉ phục vụ shipper và OPS. Role khác không được phép dùng app.
  bool get canUseApp => isShipper || isOpsCapable;

  factory UserSession.fromData(Map<String, dynamic> data) {
    return UserSession(
      user: AuthUser.fromJson(
        data['user'] is Map<String, dynamic>
            ? data['user'] as Map<String, dynamic>
            : <String, dynamic>{},
      ),
      roles: _stringList(data['roles']),
      permissions: _stringList(data['permissions']),
      defaultModule: DefaultModule.fromString(
        data['default_module']?.toString(),
      ),
    );
  }

  static List<String> _stringList(Object? value) {
    if (value is List) {
      return value.map((e) => e.toString()).toList();
    }
    return const [];
  }
}
