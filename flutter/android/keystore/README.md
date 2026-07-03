# Keystore ký bản release

Thư mục chứa `release.jks` — keystore ký app Android. File keystore và
`../key.properties` đã được gitignore, **không bao giờ commit**.

## ⚠️ BACKUP BẮT BUỘC

**Mất keystore = mất vĩnh viễn khả năng update app** trên máy user / Play Store
(bản mới khác chữ ký sẽ không cài đè được — user phải gỡ cài lại, mất dữ liệu local).
Google không cấp lại keystore.

Sau khi tạo keystore, backup ngay 2 file sau vào nơi an toàn (password manager,
két số, ổ cứng offline — tối thiểu 2 nơi):

1. `keystore/release.jks`
2. `key.properties` (chứa mật khẩu)

## Thông tin keystore hiện tại

- File: `release.jks`
- Alias: `shipper_ops_release`
- Loại: RSA 2048, hạn 30 năm (tạo 2026-07-03)
- Mật khẩu: xem `../key.properties` (không ghi vào file này)

## Mô hình per-KH (xem docs/DE_XUAT_TINH_NANG_CHAO_GIA — mục C2)

Nếu KH tự đứng tên app trên store của họ: tạo keystore RIÊNG cho từng KH
(lệnh trong `key.properties.example`), lưu tách bạch theo KH. Keystore này
chỉ dùng cho bản phát hành do mình đứng tên.

## Kiểm tra nhanh

```bash
# Xem thông tin keystore
keytool -list -v -keystore release.jks

# Xác nhận APK/AAB đã ký đúng key release (fingerprint phải khớp keystore)
keytool -printcert -jarfile app-release.apk
```
