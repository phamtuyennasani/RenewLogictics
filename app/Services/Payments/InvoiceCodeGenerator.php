<?php

namespace App\Services\Payments;

use App\Enums\InvoiceTypeEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceCodeGenerator
{
    public function generate(InvoiceTypeEnum $type, ?Carbon $when = null): string
    {
        $when ??= Carbon::now();
        $datePart = $when->format('Ymd');
        $prefix = $type->codePrefix();

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = sprintf('%s-%s-%04d', $prefix, $datePart, random_int(0, 9999));

            if (! $this->codeExists($type, $candidate)) {
                return $candidate;
            }
        }

        return sprintf('%s-%s-%s', $prefix, $datePart, strtoupper(Str::random(6)));
    }

    public function generatePaymentCode(string $invoiceCode): string
    {
        $clean = preg_replace('/[^A-Z0-9]/i', '', $invoiceCode) ?: 'PAY';

        return strtoupper($clean) . strtoupper(Str::random(4));
    }

    protected function codeExists(InvoiceTypeEnum $type, string $candidate): bool
    {
        $table = $type === InvoiceTypeEnum::THU ? 'congno_payments' : 'congno_daily_payments';

        return DB::table($table)->where('ma_hoa_don', $candidate)->exists();
    }
}
