# Vận hành Queue Worker (push notification FCM)

Cập nhật: 2026-07-03

## Bối cảnh

Từ 2026-07-03, `QUEUE_CONNECTION=database` (trước đó `sync`). Các job push FCM
(`SendPickupAssignedPush`, `SendPickupAssignedOpsPush`, `SendOrderAssignedPush`,
`SendNotificationPush`) không còn chạy ngay trong HTTP request — chúng được ghi
vào bảng `jobs` và cần **queue worker chạy nền** để xử lý.

**Nếu không chạy worker: đơn/pickup vẫn gán bình thường, nhưng KHÔNG có push
notification nào tới app shipper/OPS** (job nằm chờ trong bảng `jobs`).

Job đã cấu hình sẵn: `tries = 3`, `backoff = 10` giây. Job lỗi quá 3 lần rơi vào
bảng `failed_jobs`.

## Phương án A — VPS có supervisor (khuyến nghị)

File mẫu: `docs/deploy/supervisor-queue-worker.conf`. Cài đặt:

```bash
sudo cp docs/deploy/supervisor-queue-worker.conf /etc/supervisor/conf.d/renewlogictics-worker.conf
# Sửa đường dẫn `command` và `user` cho đúng server rồi:
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status renewlogictics-worker:*
```

## Phương án B — Shared hosting / cPanel (không có supervisor)

Thêm cron job chạy mỗi phút, worker tự thoát khi hết job (không chiếm process):

```
* * * * * cd /path/to/RenewLogictics && php artisan queue:work --stop-when-empty --tries=3 --max-time=55 >> /dev/null 2>&1
```

Đánh đổi: push có thể trễ tối đa ~1 phút so với worker daemon.

## Môi trường dev

`composer dev` đã kèm sẵn `queue:listen` (xem `composer.json` scripts) — không
cần làm gì thêm.

## Quy tắc khi deploy code mới

Worker daemon **cache code trong RAM** — sau mỗi lần deploy PHẢI chạy:

```bash
php artisan queue:restart
```

(worker tự thoát sau job hiện tại, supervisor/cron khởi động lại bản mới).
Quên bước này = worker chạy code cũ, bug đã fix vẫn tái diễn trên push.

## Giám sát & xử lý sự cố

| Việc | Lệnh |
|---|---|
| Job đang chờ | `php artisan queue:monitor database:default` hoặc `SELECT COUNT(*) FROM jobs;` |
| Job đã fail | `php artisan queue:failed` |
| Chạy lại job fail | `php artisan queue:retry all` (hoặc theo id) |
| Xóa job fail cũ | `php artisan queue:flush` |
| Test nhanh worker sống | Gán shipper vào 1 pickup → bảng `jobs` tăng rồi về 0 trong vài giây |

Dấu hiệu worker chết: bảng `jobs` tăng dần không giảm → kiểm tra
`supervisorctl status` (phương án A) hoặc cron log (phương án B).

## Ứng viên chuyển sang queue sau này (chưa làm)

- 2 chỗ gửi mail e-invoice đang gửi sync trong request
  (`⚡payment.blade.php` và `congno/⚡show`): user bấm gửi thủ công nên chấp
  nhận được, nhưng nếu SMTP chậm gây phàn nàn thì đổi `->send()` thành
  `->queue()` (Mailable đã có sẵn trait `Queueable`).
