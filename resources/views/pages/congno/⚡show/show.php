<?php
use App\Enums\DebtStatusEnum;
use App\Models\CongNo;
use App\Models\CongNoPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Flux\Flux;

new #[Layout('layouts.app')] #[Title('Chi tiết công nợ')] class extends Component {
    public CongNo $debt;
    public string $paymentAmount = '';
    public ?string $paymentDate = null;
    public ?string $paymentMethod = null;
    public ?string $paymentReference = null;
    public ?string $paymentNote = null;

    public function mount(string $id): void
    {
        $this->debt = CongNo::query()
            ->with([
                'sale:id,fullname,username,code',
                'customer:id,fullname,username,code,phone,email',
                'creator:id,fullname,username,code',
                'ketoan:id,fullname,username,code',
                'details.order.dichvu:id,namevi',
                'details.order.chiNhanhNhanHang:id,namevi',
                'details.order.packages',
                'payments.user:id,fullname,username,code',
            ])
            ->where(function ($query) use ($id) {
                $query->where('uuid', $id);

                if (ctype_digit($id)) {
                    $query->orWhere('id', (int) $id);
                }
            })
            ->firstOrFail();

        abort_unless($this->canView(), 403);
        $this->paymentDate = now()->format('Y-m-d\TH:i');
    }

    public function confirmDebt(): void
    {
        abort_unless($this->canManage(), 403);

        if (! in_array($this->debt->status, [DebtStatusEnum::MOI_TAO, DebtStatusEnum::QUA_HAN], true)) {
            Flux::toast(heading: 'Không hợp lệ', text: 'Chỉ công nợ mới tạo hoặc quá hạn mới có thể chốt lại.', variant: 'warning');
            return;
        }

        DB::transaction(function () {
            $this->debt->forceFill([
                'status' => DebtStatusEnum::DA_CHOT_CUOC,
                'id_success' => auth()->id(),
                'id_ketoan' => auth()->user()->hasRole('ketoan') ? auth()->id() : $this->debt->id_ketoan,
                'ngaychothoadon' => now(),
                'hanthanhtoan' => now()->addDays((int) $this->debt->songaythanhtoan)->startOfDay(),
            ])->save();

            $this->debt->orders()->update(['customer_payment_status' => DebtStatusEnum::DA_CHOT_CUOC->value]);
        });

        $this->reloadDebt();
        Flux::toast(heading: 'Đã chốt cước', text: 'Công nợ đã được chuyển sang trạng thái đã chốt cước.', variant: 'success');
    }

    public function markOverdue(): void
    {
        abort_unless($this->canManage(), 403);

        $this->debt->forceFill(['status' => DebtStatusEnum::QUA_HAN])->save();
        $this->reloadDebt();
        Flux::toast(heading: 'Đã cập nhật', text: 'Công nợ đã chuyển sang trạng thái quá hạn.', variant: 'success');
    }

    public function addPayment(): void
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validate([
            'paymentAmount' => ['required', 'regex:/^[0-9.,]+$/'],
            'paymentDate' => ['required', 'date'],
            'paymentMethod' => ['nullable', 'string', 'max:100'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'paymentNote' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'paymentAmount' => 'Số tiền thanh toán',
            'paymentDate' => 'Ngày thanh toán',
        ]);

        $amount = $this->number($data['paymentAmount']);

        if ($amount <= 0) {
            Flux::toast(heading: 'Số tiền không hợp lệ', text: 'Vui lòng nhập số tiền lớn hơn 0.', variant: 'warning');
            return;
        }

        DB::transaction(function () use ($data, $amount) {
            CongNoPayment::create([
                'id_congno' => $this->debt->id,
                'id_user' => auth()->id(),
                'amount' => $amount,
                'paid_at' => Carbon::parse($data['paymentDate']),
                'method' => $data['paymentMethod'],
                'reference' => $data['paymentReference'],
                'note' => $data['paymentNote'],
            ]);

            $this->debt->syncPaidAmountFromPayments();

            $orderStatus = $this->debt->remaining_amount <= 0
                ? DebtStatusEnum::DA_THANH_TOAN->value
                : DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value;

            $this->debt->orders()->update([
                'customer_payment_status' => $orderStatus,
                'customer_paid_at' => $orderStatus === DebtStatusEnum::DA_THANH_TOAN->value ? now() : null,
            ]);
        });

        $this->paymentAmount = '';
        $this->paymentMethod = null;
        $this->paymentReference = null;
        $this->paymentNote = null;
        $this->paymentDate = now()->format('Y-m-d\TH:i');
        $this->reloadDebt();

        Flux::toast(heading: 'Đã ghi nhận thanh toán', text: 'Số tiền đã được cập nhật vào công nợ.', variant: 'success');
    }

    public function removeOrder(int $detailId): void
    {
        abort_unless($this->canManage(), 403);

        if ($this->debt->status === DebtStatusEnum::DA_THANH_TOAN) {
            Flux::toast(heading: 'Không thể xóa', text: 'Công nợ đã thanh toán không thể xóa order.', variant: 'warning');
            return;
        }

        $detail = $this->debt->details()->whereKey($detailId)->firstOrFail();
        $detail->delete();
        $this->debt->syncTotalsFromDetails();
        $this->reloadDebt();

        Flux::toast(heading: 'Đã xóa order', text: 'Order đã được gỡ khỏi công nợ.', variant: 'success');
    }

    protected function reloadDebt(): void
    {
        $this->debt = CongNo::query()
            ->with([
                'sale:id,fullname,username,code',
                'customer:id,fullname,username,code,phone,email',
                'creator:id,fullname,username,code',
                'ketoan:id,fullname,username,code',
                'details.order.dichvu:id,namevi',
                'details.order.chiNhanhNhanHang:id,namevi',
                'details.order.packages',
                'payments.user:id,fullname,username,code',
            ])
            ->findOrFail($this->debt->id);
    }

    public function canView(): bool
    {
        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'manager', 'ketoan'])) {
            return true;
        }

        if ($user->hasRole('sale')) {
            return (int) $this->debt->id_sale === (int) $user->id;
        }

        if ($user->hasRole('ctv')) {
            return (int) $this->debt->id_customer === (int) $user->id || (int) $this->debt->id_ctv === (int) $user->id;
        }

        return false;
    }

    public function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    protected function number(mixed $value): float
    {
        return (float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0));
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function render()
    {
        return $this->view();
    }
};

