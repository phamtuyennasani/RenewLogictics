# Hệ Thống Zalo Mini App

Mini App cho hai luồng đầu tiên:

- Tra cước tham khảo theo dịch vụ, quốc gia nhận, cân nặng và kích thước kiện.
- Gửi yêu cầu lấy hàng để đội vận hành liên hệ xác nhận.

Giao diện dùng ZaUI Components theo cấu trúc Mini App chuẩn: `App`, `Page`, `Header`, `Tabs`, `Box`, `Text`, `Input`, `Select`, `Button`, `Icon`. Cách tổ chức màn hình tham khảo template `Zalo-MiniApp/zaui-uni`: chia theo tính năng, có mock/API service riêng và dùng component hệ thống thay vì control HTML thuần.

## Chạy local

```bash
npm install
npm run dev
```

Mặc định app chạy với dữ liệu mock nếu chưa cấu hình API. Để nối Laravel:

```bash
cp .env.example .env
```

Sửa `VITE_API_BASE_URL` trỏ về domain Laravel, ví dụ:

```env
VITE_API_BASE_URL=http://hethong-laravel.test
```

## API Laravel đang dùng

- `GET /api/zalo-mini-app/bootstrap`
- `GET /api/zalo-mini-app/countries?service_id=...`
- `POST /api/zalo-mini-app/quote`
- `POST /api/zalo-mini-app/shipping-requests`

Trước khi gửi yêu cầu thật, chạy migration ở Laravel:

```bash
php artisan migrate
```

## Triển khai Zalo

Sau khi có Mini App ID trên Zalo Platform, dùng CLI:

```bash
npm run build
npx zmp login
npx zmp deploy
```
