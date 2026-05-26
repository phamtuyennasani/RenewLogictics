<?php
use App\Enums\InvoicePaymentStatusEnum;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.app')] #[Title('Hóa đơn thu')] class extends \Livewire\Component {
    public string $keyword = '';
    public string $status = '';
    public ?int $saleId = null;
    public ?int $customerId = null;
    public ?string $fromDate = null;
    public ?string $toDate = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->fromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->toDate ??= now()->format('Y-m-d');
        $this->saleId ??= $user->hasRole('sale') ? $user->id : null;
        $this->customerId ??= $user->hasRole('ctv') ? $user->id : null;
    }

    public function routes(): array
    {
        return [
            'datatable' => route('invoice.datatable'),
            'approve' => url('/hoa-don-thu'),
            'cancel' => url('/hoa-don-thu'),
            'cash' => url('/hoa-don-thu'),
            'qr' => url('/hoa-don-thu'),
            'regenerateQr' => url('/hoa-don-thu'),
            'confirmCash' => url('/hoa-don-thu'),
            'rejectCash' => url('/hoa-don-thu'),
            'resetPaymentChannel' => url('/hoa-don-thu'),
            'sales' => route('invoice.sales'),
            'customers' => route('invoice.customers'),
        ];
    }

    public function invoiceStatuses(): array
    {
        return InvoicePaymentStatusEnum::cases();
    }

    public function sales()
    {
        return User::role('sale')->orderBy('fullname')->get(['id', 'fullname', 'username', 'code']);
    }

    public function customers()
    {
        return User::role('ctv')
            ->when($this->saleId, fn ($q) => $q->where('id_sale', $this->saleId))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code', 'id_sale']);
    }

    public function resetFilters(): void
    {
        $this->keyword = '';
        $this->status = '';
        $this->saleId = auth()->user()->hasRole('sale') ? auth()->id() : null;
        $this->customerId = auth()->user()->hasRole('ctv') ? auth()->id() : null;
        $this->fromDate = now()->subDays(30)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 0, ',', '.').' đ';
    }

    public function render()
    {
        return $this->view();
    }
};
