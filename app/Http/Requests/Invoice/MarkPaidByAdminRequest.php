<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class MarkPaidByAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $this->user()->can('markPaid', $invoice);
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_at.date' => 'Ngày thanh toán không hợp lệ',
            'paid_at.before_or_equal' => 'Ngày thanh toán không được ở tương lai',
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự',
        ];
    }
}
