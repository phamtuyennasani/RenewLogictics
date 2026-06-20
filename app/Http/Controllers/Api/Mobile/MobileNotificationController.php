<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\NotificationRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileNotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $role = $user->roles->first()?->name;
        $perPage = (int) ($validated['per_page'] ?? 30);

        $query = News::query()
            ->with('user:id,fullname,username')
            ->where('type', 'thongbao')
            ->where('status', 'active')
            ->when($role !== 'admin', fn ($q) => $q->whereJsonContains('options2->roles', $role))
            ->withExists([
                'reads as is_read' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->latest();

        $paginator = $query->paginate($perPage);

        return $this->ok([
            'items' => collect($paginator->items())
                ->map(fn (News $item) => $this->notificationPayload($item))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 'OK');
    }

    public function markRead(Request $request, int $notification): JsonResponse
    {
        $item = $this->visibleNotifications($request)->find($notification);

        if (! $item) {
            return $this->fail('Không tìm thấy thông báo.', 404);
        }

        NotificationRead::updateOrCreate(
            ['user_id' => $request->user()->id, 'news_id' => $item->id],
            ['read_at' => now()],
        );

        return $this->ok(null, 'Đã đánh dấu đã đọc.');
    }

    protected function visibleNotifications(Request $request)
    {
        $user = $request->user();
        $role = $user->roles->first()?->name;

        return News::query()
            ->where('type', 'thongbao')
            ->where('status', 'active')
            ->when($role !== 'admin', fn ($q) => $q->whereJsonContains('options2->roles', $role));
    }

    protected function notificationPayload(News $item): array
    {
        $content = trim(strip_tags(html_entity_decode((string) $item->contentvi)));

        return [
            'id' => $item->id,
            'title' => $item->namevi,
            'content' => $content,
            'excerpt' => Str::limit($content, 120),
            'author' => $item->user?->fullname ?: $item->user?->username ?: 'Hệ thống',
            'created_at' => $item->created_at?->toIso8601String(),
            'is_read' => (bool) $item->is_read,
        ];
    }
}
