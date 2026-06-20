<?php

namespace App\Observers;

use App\Jobs\SendNotificationPush;
use App\Models\News;

class NewsObserver
{
    /**
     * Thông báo (type='thongbao') vừa tạo ở trạng thái active → gửi push.
     */
    public function created(News $news): void
    {
        if ($news->type === 'thongbao' && $news->status === 'active') {
            $this->dispatchNotification($news);
        }
    }

    /**
     * Thông báo được bật từ inactive → active (publish) → gửi push.
     *
     * Dùng wasChanged + getOriginal để chỉ bắn đúng lần chuyển sang active,
     * tránh gửi trùng khi save lại mà không đổi trạng thái.
     */
    public function updated(News $news): void
    {
        if ($news->type !== 'thongbao') {
            return;
        }

        if (! $news->wasChanged('status')) {
            return;
        }

        if ($news->status === 'active' && $news->getOriginal('status') !== 'active') {
            $this->dispatchNotification($news);
        }
    }

    private function dispatchNotification(News $news): void
    {
        // Tắt push toàn cục (chưa có app để nhận) → không enqueue job rác.
        if (! config('services.firebase.push_enabled')) {
            return;
        }

        SendNotificationPush::dispatch((int) $news->id);
    }
}
