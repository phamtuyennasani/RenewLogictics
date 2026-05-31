<?php

namespace App\Mail;

use App\Models\CongNo;
use App\Models\CongNoEInvoice;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $company;

    public function __construct(
        public CongNoEInvoice $einvoice,
        public CongNo $debt,
    ) {
        $options = Setting::query()->first()?->options ?? [];

        $this->company = [
            'name' => $options['company_name'] ?? config('app.name'),
            'short_name' => $options['company_short_name'] ?? null,
            'address' => $options['company_address'] ?? null,
            'phone' => $options['company_phone'] ?? null,
            'email' => $options['company_email'] ?? null,
            'tax_code' => $options['company_tax_code'] ?? null,
            'website' => $options['company_website'] ?? null,
        ];
    }

    public function envelope(): Envelope
    {
        $number = $this->einvoice->invoice_number ?: $this->einvoice->reference;
        $companyName = $this->company['short_name'] ?: $this->company['name'];

        return new Envelope(
            subject: "Hóa đơn điện tử #{$number} - {$companyName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.einvoice',
            with: [
                'einvoice' => $this->einvoice,
                'debt' => $this->debt,
                'company' => $this->company,
                'customerName' => $this->resolveCustomerName(),
                'orderCodes' => $this->resolveOrderCodes(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->einvoice->pdf_path) {
            $pdfPath = public_path($this->einvoice->pdf_path);

            if (is_file($pdfPath)) {
                $fileName = 'HoaDon-' . ($this->einvoice->invoice_number ?: $this->einvoice->reference) . '.pdf';

                $attachments[] = Attachment::fromPath($pdfPath)
                    ->as($fileName)
                    ->withMime('application/pdf');
            }
        }

        return $attachments;
    }

    protected function resolveCustomerName(): string
    {
        $customer = $this->debt->customer;
        $company = data_get($customer, 'options.company', []);

        return data_get($company, 'company_name')
            ?: ($customer?->fullname ?: ($customer?->username ?: 'Quý khách'));
    }

    /**
     * @return array<int, string>
     */
    protected function resolveOrderCodes(): array
    {
        return $this->debt->details()
            ->with('order:id,id_bill')
            ->get()
            ->map(fn ($detail) => $detail->order_code ?: $detail->order?->id_bill ?: ('#' . $detail->id_order))
            ->filter()
            ->values()
            ->all();
    }
}
