<?php

namespace App\Services\Payments\Contracts;

use App\Services\Payments\Data\PaymentIntentData;
use App\Services\Payments\Data\PaymentRequestData;
use App\Services\Payments\Data\PaymentWebhookData;
use Illuminate\Http\Request;

interface PaymentProvider
{
    public function key(): string;

    public function createPayment(PaymentRequestData $data): PaymentIntentData;

    public function parseWebhook(Request $request): PaymentWebhookData;
}
