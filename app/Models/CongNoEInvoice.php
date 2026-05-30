<?php

namespace App\Models;

use App\Services\EInvoices\Data\EInvoiceStatusData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CongNoEInvoice extends Model
{
    use HasFactory;

    protected $table = 'congno_einvoices';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'id_congno',
        'id_user',
        'provider',
        'provider_account_id',
        'reference',
        'template_code',
        'invoice_series',
        'invoice_number',
        'issued_date',
        'tracking_code',
        'provider_reference_code',
        'tracking_url',
        'invoice_url',
        'pdf_path',
        'xml_path',
        'files_downloaded_at',
        'amount',
        'currency',
        'status',
        'buyer',
        'items',
        'provider_payload',
        'notes',
        'error_message',
        'issued_at',
        'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_date' => 'date',
        'buyer' => 'array',
        'items' => 'array',
        'provider_payload' => 'array',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'files_downloaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CongNoEInvoice $einvoice): void {
            $einvoice->uuid ??= (string) Str::uuid();
            $einvoice->status ??= self::STATUS_PENDING;
            $einvoice->currency ??= 'VND';
        });
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function congNo()
    {
        return $this->belongsTo(CongNo::class, 'id_congno')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function creator()
    {
        return $this->user();
    }

    // =========================================================================
    // Status helpers
    // =========================================================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Đang xử lý',
            self::STATUS_SUCCESS => 'Thành công',
            self::STATUS_FAILED => 'Thất bại',
            self::STATUS_CANCELLED => 'Đã hủy',
            default => 'Không xác định',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-amber-100 text-amber-700',
            self::STATUS_SUCCESS => 'bg-emerald-100 text-emerald-700',
            self::STATUS_FAILED => 'bg-red-100 text-red-700',
            self::STATUS_CANCELLED => 'bg-neutral-100 text-neutral-700',
            default => 'bg-neutral-100 text-neutral-500',
        };
    }

    // =========================================================================
    // Actions
    // =========================================================================

    /**
     * Cập nhật trạng thái từ EInvoiceStatusData.
     */
    public function updateFromStatusData(EInvoiceStatusData $data): void
    {
        $updates = [
            'status' => $data->status,
        ];

        if ($data->invoiceNumber) {
            $updates['invoice_number'] = $data->invoiceNumber;
        }

        if ($data->providerReferenceCode) {
            $updates['provider_reference_code'] = $data->providerReferenceCode;
        }

        if ($data->message && $data->status === EInvoiceStatusData::STATUS_FAILED) {
            $updates['error_message'] = $data->message;
        }

        // Lấy pdf_url / xml_url từ raw response (SePay trả trong response.invoice)
        $pdfUrl = $data->raw['pdf_url']
            ?? $data->raw['invoice']['pdf_url']
            ?? null;

        if ($pdfUrl && empty($this->invoice_url)) {
            $updates['invoice_url'] = $pdfUrl;
        }

        // Lưu raw payload mới nhất (bổ sung thông tin chi tiết)
        if (! empty($data->raw)) {
            $updates['provider_payload'] = array_merge(
                (array) ($this->provider_payload ?? []),
                $data->raw
            );
        }

        if ($data->status === EInvoiceStatusData::STATUS_SUCCESS && ! $this->issued_at) {
            $updates['issued_at'] = now();
        }

        $this->forceFill($updates)->save();
    }

    /**
     * Đánh dấu thất bại với message.
     */
    public function markFailed(string $message): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
        ])->save();
    }

    /**
     * Đánh dấu thành công.
     */
    public function markSuccess(?string $invoiceNumber = null, ?string $invoiceUrl = null): void
    {
        $updates = [
            'status' => self::STATUS_SUCCESS,
            'issued_at' => now(),
        ];

        if ($invoiceNumber) {
            $updates['invoice_number'] = $invoiceNumber;
        }

        if ($invoiceUrl) {
            $updates['invoice_url'] = $invoiceUrl;
        }

        $this->forceFill($updates)->save();
    }

    /**
     * Hủy hóa đơn.
     */
    public function markCancelled(?string $reason = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'error_message' => $reason,
        ])->save();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Tạo reference code cho hóa đơn điện tử.
     */
    /**
     * Tải file PDF/XML từ provider, lưu vào storage local.
     * Trả về true nếu tải được ít nhất 1 file.
     */
    public function downloadAndStoreFiles(?string $disk = null): bool
    {
        if (! $this->isSuccess() || ! $this->tracking_code) {
            return false;
        }

        $disk = $disk ?: config('filesystems.default', 'local');
        $storage = \Illuminate\Support\Facades\Storage::disk($disk);

        $driver = app(\App\Services\EInvoices\EInvoiceProviderManager::class)
            ->driver($this->provider);

        $folder = 'einvoices/' . now()->format('Y/m');
        $baseName = $this->invoice_number
            ? $this->provider . '-' . $this->invoice_number
            : $this->provider . '-' . substr($this->reference, 0, 40);
        $baseName = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName);

        $updates = [];
        $downloaded = false;

        // PDF
        if (empty($this->pdf_path)) {
            try {
                $pdfBinary = $driver->download($this->tracking_code, 'pdf');
                $pdfPath = $folder . '/' . $baseName . '.pdf';
                $storage->put($pdfPath, $pdfBinary);
                $updates['pdf_path'] = $pdfPath;
                $downloaded = true;
            } catch (\Throwable) {
                // Bỏ qua, sẽ thử lại sau
            }
        }

        // XML
        if (empty($this->xml_path)) {
            try {
                $xmlBinary = $driver->download($this->tracking_code, 'xml');
                $xmlPath = $folder . '/' . $baseName . '.xml';
                $storage->put($xmlPath, $xmlBinary);
                $updates['xml_path'] = $xmlPath;
                $downloaded = true;
            } catch (\Throwable) {
                // Bỏ qua
            }
        }

        if ($downloaded) {
            $updates['files_downloaded_at'] = now();
            $this->forceFill($updates)->save();
        }

        return $downloaded;
    }

    /**
     * Có file PDF lưu local không.
     */
    public function hasLocalPdf(): bool
    {
        return ! empty($this->pdf_path);
    }

    /**
     * Có file XML lưu local không.
     */
    public function hasLocalXml(): bool
    {
        return ! empty($this->xml_path);
    }

    public static function generateReference(CongNo $debt): string
    {
        $prefix = 'EINV';
        $debtCode = $debt->sohoadon ?: ('CN' . $debt->id);

        return $prefix . '-' . $debtCode . '-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));
    }

    /**
     * Kiểm tra công nợ đã có hóa đơn điện tử thành công chưa.
     */
    public static function hasSuccessfulInvoice(int $congNoId): bool
    {
        return self::query()
            ->where('id_congno', $congNoId)
            ->where('status', self::STATUS_SUCCESS)
            ->exists();
    }

    /**
     * Lấy hóa đơn điện tử mới nhất của công nợ.
     */
    public static function latestForCongNo(int $congNoId): ?self
    {
        return self::query()
            ->where('id_congno', $congNoId)
            ->latest('id')
            ->first();
    }
}
