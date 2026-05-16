<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Actions\Order\CreateOrderAction;
use App\DataTransferObjects\OrderFormData;
use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use App\Models\News;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Tạo đơn hàng')] class extends Component
{
    use WithFileUploads;

    public ?int $idSale = null;
    public ?int $idCustomer = null;
    public $listSale = [];
    public $listCustomer = [];
    public $listReceiver = [];
    public $listSender = [];
    public $itemServices;
    public $phuphihaiquan = [];
    public $invoices = [];
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
    public array $packages = [
        [
            'number_of_package' => 1,
            'package_type' => null,
            'length' => '',
            'width' => '',
            'height' => '',
            'g_weight' => '',
            'v_weight' => 0,
            'c_weight' => 0,
            'row_g_weight' => 0,
            'row_v_weight' => 0,
            'row_c_weight' => 0,
        ],
    ];
    public array $ghichu = [
        'note' => '',
        'photos' => [],
    ];
    public array $newPhotos = [];
    public bool $saveInfoSender = false;
    public bool $saveInfoReceiver = false;
    public bool $agreedToTerms = false;
    public bool $showSaleSelector = true;
    #[Locked]
    public float $dim;

    public function mount(): void
    {
        $user = auth()->user();

        $this->dim = \App\Models\Setting::selectRaw("JSON_UNQUOTE(JSON_EXTRACT(options, '$.dim')) as dim")->value('dim');
        if ($user->hasRole('sale')) {
            $this->idSale = $user->id;
            $this->showSaleSelector = false;
        } elseif ($user->hasRole('ctv')) {
            $this->idCustomer = $user->id;
            $this->dim = $user->options['dim'] ?? $this->dim;
            $this->idSale = $user->id_sale;
            $this->showSaleSelector = false;
        }

        $this->listSale = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'SALE'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username','code'])
            ->toArray();

        if ($this->idSale) {
            $this->loadCustomersBySale($this->idSale);
        }
        $this->syncReceivers();
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
    }
    #[On('serviceProductUpdated')]
    public function handleServiceUpdated(string $tensanpham): void{
        $this->service['tensanpham'] = $tensanpham;
    }
    
    public function goBack(): void{
        redirect()->route('orders.index');
    }

    public function showRequiredFieldsToast(): void
    {
        Flux::toast(
            duration: 200000,
            heading: 'Cảnh báo',
            text: 'Bạn cần nhập đầy đủ các trường dữ liệu bắt buộc',
            variant: 'danger'
        );
    }

    protected function normalizeAssignmentByRole(): void
    {
        $user = auth()->user();

        if ($user->hasRole('sale')) {
            $this->idSale = $user->id;

            if ($this->idCustomer) {
                $isValidCtv = User::query()
                    ->whereKey($this->idCustomer)
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
            $this->idCustomer = $user->id;
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

        if ($this->idCustomer) {
            $isValidCustomer = User::query()
                ->whereKey($this->idCustomer)
                ->where('id_sale', $this->idSale)
                ->whereHas('roles', fn ($q) => $q->where('name', 'ctv'))
                ->exists();

            if (!$isValidCustomer) {
                throw new AuthorizationException('CTV không thuộc sale đã chọn.');
            }
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

    protected function loadCustomersBySale(int $saleId): void
    {
        $this->listCustomer = User::query()
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

    protected function refreshCustomerSelectOptions(): void
    {
        $this->js("
            let listCTV = " . json_encode(array_map(function($item) {
                return "<option value='{$item['id']}' data-attr='" . htmlentities(json_encode($item)) . "'>AccNo. {$item['code']} - {$item['fullname']} - {$item['phone']} - {$item['email']} - {$item['company_name']}</option>";
            }, $this->listCustomer)) . ";
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
    protected function loadReceiversByCtv(int $ctvId, string $ctvType): void
    {
        $this->listReceiver = Member::query()
            ->select([
                'id',
                'company_name',
                'fullname',
                'phone',
                'email',
                'country_id',
                'address',
                'state',
                'cities',
                'postcode',
                'options',
            ])
            ->where(($ctvType=='khachhang'?'id_ctv':'id_sender'), $this->sender['id'])
            ->where('type', 'receiver')
            ->orderBy('fullname')
            ->get()
            ->map(function (Member $member) {
                $options = $member->options ?? [];
                return [
                    'id' => $member->id,
                    'company' => $member->company_name,
                    'fullname' => $member->fullname,
                    'phone' => $member->phone,
                    'email' => $member->email,
                    'mavung' => $options['mavung'] ?? '',
                    'country_id' => $options['id_country'] ?? $member->country_id,
                    'address' => $options['address'] ?? $member->address,
                    'state' => $options['state'] ?? $member->state,
                    'city' => $options['city'] ?? $member->cities,
                    'postcode' => $options['postcode'] ?? $member->postcode,
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
                    $item['postcode'] ?? '',
                    $item['address'] ?? '',
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
        if ($this->sender['id']) {
            $this->loadReceiversByCtv($this->sender['id'],$this->sender['type']);
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
        $this->listSender = [];
        if (!$value) {
            $this->listCustomer = [];
            $this->refreshCustomerSelectOptions();
            $this->syncReceivers();
            return;
        }
        $this->loadCustomersBySale((int) $value);
        $this->refreshCustomerSelectOptions();
        $this->syncReceivers();
    }
    protected function syncDanhSachGui(): void{
        if(empty($this->sender['id'])) $this->listSender = [];
        else{
            $CustomerSelected = User::query()
                ->select([
                    'id',
                    'fullname',
                    'username',
                    'code',
                    'email',
                    'phone',
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(options, '$.company.company_name')) as company_name"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(options, '$.company.company_short_name')) as company_short_name"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(options, '$.company.address_detail')) as address"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(options, '$.company.city_id')) as city_id"),
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(options, '$.company.ward_id')) as ward_id"),
                    DB::raw("'khachhang' as type")
                ])
                ->whereKey($this->sender['id'])
                ->first();
            $this->listSender = Member::query()
                ->select([
                    'id',
                    'company_name',
                    'fullname',
                    'phone',
                    'email',
                    'code',
                    'address',
                    'id_ward',
                    DB::raw("id_province as city_id"),
                ])
                ->where('type', 'sender')
                ->where('id_sale', $this->idSale)
                ->where(function($q){
                    $q->where('id_ctv', $this->sender['id'])->orWhere('id_sender', $this->sender['id']);
                })
                ->orderBy('company_name')
                ->get()
                ->map(function (Member $member) {
                    return [
                        'id' => $member->id,
                        'company_name' => $member->company_name,
                        'fullname' => $member->fullname,
                        'phone' => $member->phone,
                        'email' => $member->email,
                        'country' =>'VIETNAM',
                        'address' =>$member->address,
                        'city_id' =>$member->city_id,
                        'ward_id' =>$member->id_ward,
                        'type'  => 'sender',
                    ];
                })
                ->toArray();
            $this->listSender = array_merge(
                $CustomerSelected ? [[
                    'id' => $CustomerSelected->id,
                    'company_name' => $CustomerSelected->company_name,
                    'fullname' => $CustomerSelected->fullname,
                    'phone' => $CustomerSelected->phone,
                    'email' => $CustomerSelected->email,
                    'country' =>'VIETNAM',
                    'address' =>$CustomerSelected->address,
                    'city_id' =>$CustomerSelected->city_id,
                    'ward_id' =>$CustomerSelected->ward_id,
                    'type'  => 'khachhang',
                ]] : [],
                $this->listSender
            );
        }
        $this->js("
            let listDanhSachGui = " . json_encode(array_map(function($item) {
                return "<option value='{$item['id']}' data-attr='" . htmlentities(json_encode($item)) . "'>{$item['company_name']} - {$item['phone']} - {$item['email']} - {$item['fullname']}</option>";
            }, $this->listSender)) . ";
            let senderSelect = document.getElementById('danhsachgui-select');
            if (senderSelect) {
                senderSelect.innerHTML = listDanhSachGui;
                if (typeof TomSelect !== 'undefined' && senderSelect.tomselect) {
                    senderSelect.tomselect.clear();
                    senderSelect.tomselect.clearOptions();
                    senderSelect.tomselect.sync();
                }
            }
        ");
    }
    public function updatedIdCustomer(): void
    {
        $this->syncDanhSachGui();
        $this->syncReceivers();
    }
    public function updatedSender(): void
    {
        $this->syncReceivers();
    }

    public function updatedNewPhotos(): void
    {
        $this->validate([
            'newPhotos' => 'nullable|array|max:5',
            'newPhotos.*' => 'image|max:20480',
        ]);

        $currentPhotos = $this->ghichu['photos'] ?? [];
        $remainingSlots = max(0, 5 - count($currentPhotos));
        $this->ghichu['photos'] = array_merge($currentPhotos, array_slice($this->newPhotos, 0, $remainingSlots));
        $this->newPhotos = [];
    }

    public function removeOrderPhoto(int $index): void
    {
        unset($this->ghichu['photos'][$index]);
        $this->ghichu['photos'] = array_values($this->ghichu['photos'] ?? []);
    }

    public function fileSize($photo): string
    {
        if (! is_object($photo) || ! method_exists($photo, 'getSize')) {
            return '';
        }

        $bytes = (int) $photo->getSize();

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        return number_format($bytes / 1024, 1).' KB';
    }

    public function imageDimensions($photo): string
    {
        if (! is_object($photo) || ! method_exists($photo, 'getRealPath')) {
            return '';
        }

        $size = @getimagesize($photo->getRealPath());

        if (! $size) {
            return '';
        }

        return $size[0].' x '.$size[1].' px';
    }

    public function submit(bool $agreedToTerms = false): void{
        $this->agreedToTerms = $agreedToTerms;
        try {
            $this->normalizeAssignmentByRole();
            $this->validate($this->rules());
        } catch (AuthorizationException $e) {
            Flux::toast(
                duration: 200000,
                heading: 'Không có quyền',
                text: $e->getMessage(),
                variant: 'danger'
            );
            return;
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->showRequiredFieldsToast();
            return;
        }

        $sender = $this->sender;
        $receiver = $this->receiver;
        $service = $this->service;
        $packages = $this->packages;
        $phuphihaiquan = $this->phuphihaiquan;
        foreach($phuphihaiquan as $k => &$v) {
            $v['price'] = str_replace(['.', ','], '', $v['price'] ?? 0);
            $v['total'] = str_replace(['.', ','], '', $v['total'] ?? 0);
        }
        unset($v);

        foreach($service as $k => &$v) {
            if(is_numeric($v)) $v = (int) $v;
            if(in_array($k, ['tensanpham'])) $v = trim($v);
            if(is_array($v)) $v = array_map(function($item) {
                if(is_numeric($item)) return (int) $item;
                return trim($item);
            }, $v);
        }
        unset($v);
        if (!$this->agreedToTerms) {
            Flux::toast(
                duration: 200000,
                heading: 'Cảnh báo',
                text: 'Bạn cần đồng ý với chính sách tạo đơn hàng để tiếp tục',
                variant: 'danger'
            );
            return;
        }
        try {
            $order = app(CreateOrderAction::class)->execute(new OrderFormData(
                idSale: $this->idSale,
                idCustomer: $this->idCustomer,
                service: $service,
                sender: $sender,
                receiver: $receiver,
                packages: $packages,
                invoiceItems: $this->invoices,
                notes: trim((string) ($this->ghichu['note'] ?? '')),
                saveInfoSender: $this->saveInfoSender ?? false,
                saveInfoReceiver: $this->saveInfoReceiver ?? false,
                dim: $this->dim,
                phuphihaiquan: $phuphihaiquan,
                orderPhotos: $this->ghichu['photos'] ?? [],
            ));

            Flux::toast(
                duration: 5000,
                heading: 'Thành công',
                text: 'Tạo đơn hàng ' . $order->id_bill . ' thành công',
                variant: 'success'
            );
            $this->redirectRoute('orders.show', ['uuid' => $order->uuid]);
        } catch (\Exception $e) {
            Flux::toast(
                duration: 200000,
                heading: 'Lỗi',
                text: 'Có lỗi xảy ra khi tạo đơn hàng: ' . $e->getMessage(),
                variant: 'danger'
            );
        }
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
            'receiver.fullname' => 'required|string|max:255',
            'receiver.phone' => 'required|string|max:20',
            'receiver.address' => 'required|string|max:500',
            'receiver.postcode' => 'required|string|max:20',
            'packages' => 'nullable|array',
            'packages.*.number_of_package' => 'nullable|integer|min:1',
            'packages.*.length' => 'nullable|numeric|min:0.1',
            'packages.*.width' => 'nullable|numeric|min:0.1',
            'packages.*.height' => 'nullable|numeric|min:0.1',
            'packages.*.g_weight' => 'nullable|numeric|min:0.1',
        ];
    }
    protected function messages(): array
    {
        return [
            'required' => ':attribute là bắt buộc.',
            'string' => ':attribute phải là chuỗi ký tự.',
            'numeric' => ':attribute phải là số hợp lệ.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'exists' => ':attribute không hợp lệ.',
            'array' => ':attribute không hợp lệ.',
        ];
    }
    protected function validationAttributes(): array
    {
        return [
            'service.id_dichvu' => 'Dịch vụ chính',
            'service.tensanpham' => 'Tên sản phẩm',
            'sender.company' => 'Tên công ty / Khách hàng gửi',
            'sender.fullname' => 'Tên người gửi',
            'sender.phone' => 'Số điện thoại người gửi',
            'sender.address' => 'Địa chỉ người gửi',
            'receiver.company' => 'Tên công ty nhận',
            'receiver.fullname' => 'Tên người nhận',
            'receiver.phone' => 'Số điện thoại người nhận',
            'receiver.address' => 'Địa chỉ người nhận',
            'receiver.state' => 'Tỉnh / Bang',
            'receiver.city' => 'Thành phố',
            'receiver.postcode' => 'Postcode',
            'packages' => 'Danh sách kiện',
            'packages.*.number_of_package' => 'Số kiện',
            'packages.*.length' => 'Chiều dài kiện',
            'packages.*.width' => 'Chiều rộng kiện',
            'packages.*.height' => 'Chiều cao kiện',
            'packages.*.g_weight' => 'Cân nặng kiện',
        ];
    }
    #[Computed]
    public function chinhsach()
    {
        return Cache::remember('chinhsach', 3600, function() {
            return DB::table('static')->where('type', 'quy-dinh-tao-don')->first()->contentvi ?? '';
        });
    }
};
