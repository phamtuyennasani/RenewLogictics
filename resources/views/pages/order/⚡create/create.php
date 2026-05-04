<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use App\Actions\Order\CreateOrderAction;
use App\DataTransferObjects\OrderFormData;
use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\News;
new #[Layout('layouts.app')] #[Title('Tạo đơn hàng')] class extends Component
{
    public ?int $idSale = null;
    public ?int $idCtv = null;
    public ?int $idCustomer = null;
    public $listSale = [];
    public $listSender = [];
    public $listReceiver = [];
    public $itemServices;

    public array $service = [
        'id_dichvu' => null,
        'id_chitiet_dichvu' => null,
        'id_chinhanh_nhanhang' => null,
        'tensanpham' => '',
        'dichvudikem' => [],
        'tinhtrangdon' => null, 
        'hinhthucguihang' => null,
        'deliveryterm' => null,
        'lydoguihang' => null,
        'loaibuugui' => null,
    ];

    public array $sender = [
        'id' => null,
        'company' => '',
        'company_short_name' => '',
        'fullname' => '',
        'phone' => '',
        'email' => '',
        'country' => 'VIETNAM',
        'address' => '',
        'id_city' => '',
        'id_ward' => '',
        'type'  => '',
    ];

    public array $receiver = [
        'id' => null,
        'company' => '',
        'fullname' => '',
        'phone' => '',
        'email' => '',
        'mavung' => '',
        'country_id' => null,
        'address' => '',
        'state' => '',
        'city' => '',
        'postcode' => '',
        'vsvx' => false,
        'address' => '',
    ];
    public array $packages = [];
    public ?string $notes = null;
    public bool $saveInfoSender = false;
    public bool $saveInfoReceiver = false;
    public bool $agreedToTerms = false;
    public float $dim = 6000;
    public bool $showSaleSelector = true;
    public ?int $fixedIdCtv = null;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->hasRole('sale')) {
            $this->idSale = $user->id;
            $this->showSaleSelector = false;
        } elseif ($user->hasRole('ctv')) {
            $this->idCtv = $user->id;
            $this->fixedIdCtv = $user->id;
            $this->dim = $user->options['dim'] ?? 6000;
            $this->idSale = $user->id_sale;
            $this->showSaleSelector = false;
        }

        $this->listSale = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'SALE'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username','code'])
            ->toArray();

        if ($this->idSale) {
            $this->loadSendersBySale($this->idSale);
        }
        $this->syncReceivers();

        $this->packages = [
            [
                'package_type' => null,
                'length' => '',
                'width' => '',
                'height' => '',
                'g_weight' => '',
                'v_weight' => 0,
                'c_weight' => 0,
            ]
        ];
        $this->itemServices = Cache::remember('order_service_options', 3600, function() {
            return News::whereIn('type', ['dichvuchinh','dichvuchitiet', 'chinhanh', 'dichvudikem', 'loaibuugui', 'lydoguihang', 'hinhthucgui', 'deliveryterm', 'tinhtrangdon'])
                ->orderBy('numb', 'asc')
                ->get(['id', 'namevi', 'type'])->toArray();
        });

        // Gán giá trị mặc định cho service
        $itemServices = collect($this->itemServices);
        $this->service['loaibuugui'] = $itemServices->where('type', 'loaibuugui')->first()['id'] ?? null;
        $this->service['lydoguihang'] = $itemServices->where('type', 'lydoguihang')->first()['id'] ?? null;
        $this->service['hinhthucguihang'] = $itemServices->where('type', 'hinhthucgui')->first()['id'] ?? null;
        $this->service['deliveryterm'] = $itemServices->where('type', 'deliveryterm')->first()['id'] ?? null;
        $this->service['tinhtrangdon'] = $itemServices->where('type', 'tinhtrangdon')->first()['id'] ?? null;
    }
    #[On('serviceUpdated')]
    public function handleServiceUpdated(array $service): void{
    }
    #[On('senderUpdated')]
    public function handleSenderUpdated($sender): void{
        // Không cần nữa vì component con không dispatch event
        // $this->sender = array_merge($this->sender, $sender);
    }
    #[On('receiverUpdated')]
    public function handleReceiverUpdated($receiver): void{
        // Không cần nữa vì component con không dispatch event
        // $this->receiver = array_merge($this->receiver, $receiver);
    }
    #[On('packagesUpdated')]
    public function handlePackagesUpdated($packages): void{
        // Không cần nữa vì component con không dispatch event
        // $this->packages = $packages;
    }
    public function goBack(): void{
        redirect()->route('orders.index');
    }

    protected function normalizeAssignmentByRole(): void
    {
        $user = auth()->user();

        if ($user->hasRole('sale')) {
            $this->idSale = $user->id;

            if ($this->idCtv) {
                $isValidCtv = User::query()
                    ->whereKey($this->idCtv)
                    ->where('id_sale', $user->id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'ctv'))
                    ->exists();

                if (!$isValidCtv) {
                    throw new AuthorizationException('CTV không thuộc quyền quản lý của sale hiện tại.');
                }
            }

            return;
        }

        if ($user->hasRole('ctv')) {
            $this->idSale = $user->id_sale;
            $this->idCtv = $user->id;
            $this->fixedIdCtv = $user->id;
            return;
        }

        if (!$this->idSale) {
            throw new AuthorizationException('Vui lòng chọn sale phụ trách.');
        }

        $isValidSale = User::query()
            ->whereKey($this->idSale)
            ->whereHas('roles', fn ($q) => $q->where('name', 'SALE'))
            ->exists();

        if (!$isValidSale) {
            throw new AuthorizationException('Sale phụ trách không hợp lệ.');
        }

        if ($this->idCtv) {
            $isValidCtv = User::query()
                ->whereKey($this->idCtv)
                ->where('id_sale', $this->idSale)
                ->whereHas('roles', fn ($q) => $q->where('name', 'ctv'))
                ->exists();

            if (!$isValidCtv) {
                throw new AuthorizationException('CTV không thuộc sale đã chọn.');
            }
        }
    }

    public function submit(): void{
        dd($this->service, $this->sender, $this->receiver, $this->packages);
        $this->normalizeAssignmentByRole();
        $this->validate($this->rules());
        if (!$this->agreedToTerms) {
            $this->addError('agreedToTerms', 'Bạn phải đồng ý với quy định tạo đơn');
            return;
        }
        try {
            DB::beginTransaction();
            $formData = new OrderFormData(
                idSale: $this->idSale,
                idCtv: $this->idCtv,
                idCustomer: $this->idCustomer,
                service: $this->service,
                sender: $this->sender,
                receiver: $this->receiver,
                packages: $this->packages,
                notes: $this->notes,
                saveInfoSender: $this->saveInfoSender,
                saveInfoReceiver: $this->saveInfoReceiver,
                dim: $this->dim,
            );
            $order = app(CreateOrderAction::class)->execute($formData);
            DB::commit();
            session()->flash('success', "Tạo đơn hàng {$order->id_bill} thành công!");
            redirect()->route('orders.show', $order->id);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->addError('submit', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    protected function defaultSender(): array
    {
        return [
            'id' => null,
            'company' => '',
            'company_short_name' => '',
            'fullname' => '',
            'phone' => '',
            'email' => '',
            'country' => 'VIETNAM',
            'address' => '',
            'id_city' => '',
            'id_ward' => '',
            'type'  => '',
        ];
    }

    protected function resetSender(): void
    {
        $this->idCustomer = null;
        $this->sender = $this->defaultSender();
    }

    protected function defaultReceiver(): array
    {
        return [
            'id' => null,
            'company' => '',
            'tenlienhe' => '',
            'phone' => '',
            'email' => '',
            'mavung' => '',
            'country_id' => null,
            'address' => '',
            'state' => '',
            'city' => '',
            'postcode' => '',
            'vsvx' => false,
        ];
    }

    protected function resetReceiver(): void
    {
        $this->receiver = $this->defaultReceiver();
    }

    protected function loadSendersBySale(int $saleId): void
    {
        $this->listSender = User::query()
            ->select([
                'user.id',
                'user.fullname',
                'user.username',
                'user.code',
                'user.email',
                'user.phone',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(table_user.options, '$.company.company_name')) as company_name"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(table_user.options, '$.company.company_short_name')) as company_short_name"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(table_user.options, '$.company.address_detail')) as address"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(table_user.options, '$.company.city_id')) as city_id"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(table_user.options, '$.company.ward_id')) as ward_id"),
                DB::raw("'khachhang' as type")
            ])
            ->join('model_has_roles', 'user.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', 'ctv')
            ->where('user.id_sale', $saleId)
            ->orderBy('user.fullname')
            ->get()
            ->toArray();
    }

    protected function refreshSenderSelectOptions(): void
    {
        $this->js("
            let listCTV = " . json_encode(array_map(function($item) {
                return "<option value='{$item['id']}' data-attr='" . htmlentities(json_encode($item)) . "'>AccNo. {$item['code']} - {$item['fullname']} - {$item['phone']} - {$item['email']} - {$item['company_name']}</option>";
            }, $this->listSender)) . ";
            listCTV.unshift(\"<option value='0'>Người Gửi Mới</option>\");
            let senderSelect = document.getElementById('sender-select');
            if (senderSelect) {
                senderSelect.innerHTML = listCTV;
                if (typeof TomSelect !== 'undefined' && senderSelect.tomselect) {
                    senderSelect.tomselect.clear();
                    senderSelect.tomselect.clearOptions();
                    senderSelect.tomselect.sync();
                    senderSelect.tomselect.setValue(0);
                }
            }
        ");
    }

    protected function loadReceiversBySale(int $saleId): void
    {
        $this->listReceiver = Member::query()
            ->where('type', 'receiver')
            ->where('id_sale', $saleId)
            ->orderBy('fullname')
            ->get()
            ->map(function (Member $member) {
                return [
                    'id' => $member->id,
                    'company' => $member->company_name,
                    'fullname' => $member->fullname,
                    'phone' => $member->phone,
                    'email' => $member->email,
                    'mavung' => $member->options['mavung'] ?? '',
                    'country_id' => $member->options['id_country'] ?? $member->country_id,
                    'address' => $member->options['address'] ?? $member->address,
                    'state' => $member->options['state'] ?? $member->state,
                    'city' => $member->options['city'] ?? $member->cities,
                    'postcode' => $member->options['postcode'] ?? $member->postcode,
                    'vsvx' => false,
                ];
            })
            ->toArray();
    }

    protected function loadReceiversByCtv(int $ctvId): void
    {
        $this->listReceiver = Member::query()
            ->receiver()
            ->where('id_ctv', $ctvId)
            ->orderBy('fullname')
            ->get()
            ->map(function (Member $member) {
                return [
                    'id' => $member->id,
                    'company' => $member->company_name,
                    'fullname' => $member->fullname,
                    'phone' => $member->phone,
                    'email' => $member->email,
                    'mavung' => $member->options['mavung'] ?? '',
                    'country_id' => $member->options['id_country'] ?? $member->country_id,
                    'address' => $member->options['address'] ?? $member->address,
                    'state' => $member->options['state'] ?? $member->state,
                    'city' => $member->options['city'] ?? $member->cities,
                    'postcode' => $member->options['postcode'] ?? $member->postcode,
                    'vsvx' => false,
                ];
            })
            ->toArray();
    }

    protected function refreshReceiverSelectOptions(): void
    {
        $this->js("
            let listReceiver = " . json_encode(array_map(function($item) {
                $label = trim(implode(' - ', array_filter([
                    $item['company'] ?? '',
                    $item['tenlienhe'] ?? '',
                    $item['phone'] ?? '',
                    $item['email'] ?? '',
                    $item['postcode'] ?? '',
                ])));
                return "<option value='{$item['id']}' data-attr='" . htmlentities(json_encode($item)) . "'>{$label}</option>";
            }, $this->listReceiver)) . ";
            listReceiver.unshift(\"<option value='0'>Người Nhận Mới</option>\");
            let receiverSelect = document.getElementById('receiver-select');
            if (receiverSelect) {
                receiverSelect.innerHTML = listReceiver;
                if (typeof TomSelect !== 'undefined' && receiverSelect.tomselect) {
                    receiverSelect.tomselect.clear();
                    receiverSelect.tomselect.clearOptions();
                    receiverSelect.tomselect.sync();
                    receiverSelect.tomselect.setValue(0);
                }
            }
        ");
    }

    protected function syncReceivers(): void
    {
        $this->resetReceiver();
        if ($this->idCtv) {
            
            $this->loadReceiversByCtv($this->idCtv);
        } elseif ($this->idSale) {
            $this->loadReceiversBySale($this->idSale);
        } else {
            $this->listReceiver = [];
        }
        $this->refreshReceiverSelectOptions();
    }

    public function updatingIdSale($value): void{
        $user = auth()->user();
        $this->idSale = $value ? (int) $value : null;
        if ($user->hasRole('sale') || $user->hasRole('ctv')) {
            $this->idSale = $user->hasRole('sale') ? $user->id : $user->id_sale;
            return;
        }

        $this->resetSender();
        $this->idCtv = $this->fixedIdCtv;
        if (!$value) {
            $this->listSender = [];
            $this->refreshSenderSelectOptions();
            $this->syncReceivers();
            return;
        }
        $this->loadSendersBySale((int) $value);
        $this->refreshSenderSelectOptions();
        $this->syncReceivers();
    }

    public function updatingIdCtv($value): void
    {
        $user = auth()->user();
        if ($user->hasRole('ctv')) {
            $this->idCtv = $user->id;
            $this->fixedIdCtv = $user->id;
            return;
        }

        $this->idCtv = $value ? (int) $value : $this->fixedIdCtv;
        $this->syncReceivers();
    }
    protected function rules(): array{
        return [
            'service.id_dichvu' => 'required|exists:news,id',
            'service.tensanpham' => 'required|string|max:255',
            'sender.company' => 'required|string|max:255',
            'sender.fullname' => 'required|string|max:255',
            'sender.phone' => 'required|string|max:20',
            'sender.address' => 'required|string|max:500',
            'receiver.company' => 'required|string|max:255',
            'receiver.tenlienhe' => 'required|string|max:255',
            'receiver.phone' => 'required|string|max:20',
            'receiver.address' => 'required|string|max:500',
            'receiver.postcode' => 'required|string|max:20',
            'packages' => 'nullable|array',
            'packages.*.length' => 'nullable|numeric|min:0.1',
            'packages.*.width' => 'nullable|numeric|min:0.1',
            'packages.*.height' => 'nullable|numeric|min:0.1',
            'packages.*.g_weight' => 'nullable|numeric|min:0.1',
        ];
    }
};
