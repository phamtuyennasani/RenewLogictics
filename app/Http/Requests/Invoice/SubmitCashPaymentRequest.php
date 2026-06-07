<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $this->user()->can('submitPayment', $invoice);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bank_transfer', 'check'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Số tiền thanh toán là bắt buộc',
            'amount.numeric' => 'Số tiền phải là số',
            'amount.min' => 'Số tiền phải lớn hơn 0',
            'paid_at.date' => 'Ngày thanh toán không hợp lệ',
            'paid_at.before_or_equal' => 'Ngày thanh toán không được ở tương lai',
            'payment_method.required' => 'Phương thức thanh toán là bắt buộc',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ',
            'reference.max' => 'Mã tham chiếu không được vượt quá 100 ký tự',
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự',
            'attachments.max' => 'Tối đa 5 file đính kèm',
            'attachments.*.file' => 'File đính kèm không hợp lệ',
            'attachments.*.mimes' => 'File phải có định dạng: pdf, jpg, jpeg, png',
            'attachments.*.max' => 'Mỗi file không được vượt quá 5MB',
        ];
    }
}
