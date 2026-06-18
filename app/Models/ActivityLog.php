<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit log chung cho các hành động nhạy cảm (xóa đơn, công nợ, hóa đơn...).
 *
 * Dùng ActivityLog::record() để ghi — tự điền actor/ip từ request hiện tại.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'actor_id',
        'actor_name',
        'actor_role',
        'action',
        'title',
        'note',
        'snapshot',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Ghi một dòng audit log. Tự điền actor + IP từ request/auth hiện tại.
     *
     * @param  string       $action    Mã hành động, vd 'order.delete'
     * @param  string       $title     Mô tả ngắn hiển thị cho người đọc
     * @param  Model|null   $subject   Đối tượng bị tác động (lấy type/id)
     * @param  array        $snapshot  Snapshot dữ liệu trước khi xóa
     * @param  array        $metadata  Thông tin bổ sung
     * @param  string|null  $note      Ghi chú (vd lý do)
     */
    public static function record(
        string $action,
        string $title,
        ?Model $subject = null,
        array $snapshot = [],
        array $metadata = [],
        ?string $note = null,
    ): self {
        $user = auth()->user();

        return static::create([
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'actor_id' => $user?->id,
            'actor_name' => $user?->fullname ?: $user?->username,
            'actor_role' => $user?->roles?->first()?->name,
            'action' => $action,
            'title' => $title,
            'note' => $note,
            'snapshot' => $snapshot ?: null,
            'ip_address' => request()->ip(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
