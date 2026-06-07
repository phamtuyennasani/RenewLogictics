<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitOnlinePaymentRequest extends FormRequest
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
            'payment_provider' => ['required', 'string', Rule::in(['sepay', 'momo', 'vnpay', 'zalopay'])],
            'return_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Số tiền thanh toán là bắt buộc',
            'amount.numeric' => 'Số tiền phải là số',
            'amount.min' => 'Số tiền phải lớn hơn 0',
            'payment_provider.required' => 'Nhà cung cấp thanh toán là bắt buộc',
            'payment_provider.in' => 'Nhà cung cấp thanh toán không hợp lệ',
            'return_url.url' => 'URL trả về không hợp lệ',
            'cancel_url.url' => 'URL hủy không hợp lệ',
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự',
        ];
    }
}
