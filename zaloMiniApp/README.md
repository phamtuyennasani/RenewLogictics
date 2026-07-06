# Hệ Thống Logistics - Mobile Web App

Ứng dụng React + Vite ưu tiên chạy như một web app bình thường trên trình duyệt di động. Giao diện dùng bộ component ZaUI (`zmp-ui`) cho các control chính như Button, Input, Select và BottomNavigation, nhưng chưa phụ thuộc vỏ Zalo Mini App trong runtime chính. Phần kết nối Zalo sẽ được bật lại ở giai đoạn sau.

## Chức năng

- Khách: tra cước, gửi yêu cầu lấy hàng, tracking public.
- Tài khoản hệ thống: đăng nhập username/password hoặc đăng nhập bằng Zalo access token khi chạy trong Zalo Mini App, xem danh sách đơn, chi tiết đơn, timeline, tạo đơn.
- Admin: quản lý bảng giá, tạo/sửa/xóa bảng giá và dòng giá.

## Chạy local

```bash
npm install
npm run dev
```

Mặc định app chạy tại:

```text
http://localhost:3000
```

Nếu chưa cấu hình API, app dùng mock data để kiểm thử UI. Trong mock mode, username chứa `admin` sẽ mở quyền quản lý bảng giá.

Để nối Laravel thật:

```bash
cp .env.example .env
```

```env
VITE_API_BASE_URL=https://your-public-laravel-domain.com
```

## Build web

```bash
npm run build
npm run preview
```

## Lệnh Zalo để dùng sau

```bash
npm run zmp:dev
npm run zmp:build
```

Khi quay lại tích hợp Zalo, cần cấu hình domain HTTPS public, App ID, token xác thực Zalo và kiểm thử lại trên Zalo thật.

## Đăng nhập Zalo

Nút `Đăng nhập bằng Zalo` gọi `getAccessToken()` từ `zmp-sdk/apis`, thử xin `scope.userInfo` để lấy tên/avatar, sau đó gửi access token về endpoint Laravel:

```text
POST /api/zalo-mini-app/auth/zalo
```

Server vẫn là nơi xác thực token với Zalo Graph API và map sang tài khoản hệ thống.
