<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class RejectInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $this->user()->can('reject', $invoice);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Lý do từ chối là bắt buộc',
            'reason.max' => 'Lý do không được vượt quá 500 ký tự',
            'note.max' => 'Ghi chú không được vượt quá 1000 ký tự',
        ];
    }
}
