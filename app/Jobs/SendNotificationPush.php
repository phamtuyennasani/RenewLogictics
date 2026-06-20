<?php

namespace App\Jobs;

use App\Models\News;
use App\Models\UserDeviceToken;
use App\Services\Push\FcmSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Gửi push "thông báo mới" tới mọi thiết bị của các user thuộc role nhận.
 *
 * Truyền $newsId tường minh; nội dung/role nhận được đọc lại trong handle để
 * phản ánh trạng thái mới nhất tại thời điểm job chạy.
 */
class SendNotificationPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public int $newsId,
    ) {
    }

    public function handle(FcmSender $sender): void
    {
        $news = News::query()->find($this->newsId);
        if ($news === null || $news->type !== 'thongbao' || $news->status !== 'active') {
            return;
        }

        $roles = $news->options2['roles'] ?? [];
        if (! is_array($roles) || $roles === []) {
            return;
        }

        // Lấy token đang hoạt động của mọi user thuộc các role nhận, trừ người tạo.
        $tokens = UserDeviceToken::query()
            ->whereNull('revoked_at')
            ->whereHas('user', function ($q) use ($roles, $news) {
                $q->role($roles)->where('id', '!=', $news->id_user);
            })
            ->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $title = $news->namevi ?: 'Thông báo mới';
        $body = Str::limit(
            trim(strip_tags(html_entity_decode((string) $news->contentvi))),
            150,
        );

        $sender->sendToTokens(
            $tokens,
            $title,
            $body !== '' ? $body : 'Bạn có thông báo mới.',
            [
                'type' => 'notification',
                'news_id' => (string) $news->id,
            ],
        );
    }
}
