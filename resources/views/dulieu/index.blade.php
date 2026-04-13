@extends('layouts.app-with-sidebar')
@section('content')
<div class="wrap-main-page" x-data="dataTable()">
        <div class="header-page-dulieu xxl:p-vw-[10] flex items-center justify-between p-[10px] pt-0">
            <p class="xxl:!text-vw-[20] mb-0 !text-[20px] !font-bold capitalize !leading-normal">Dữ liệu /
                {{ $config['title_main'] }}
            </p>
        </div>
        <div class="main-table xxl:p-vw-[10] xxl:rounded-vw-[10] rounded-[10px] bg-white p-[10px]">
            <div class="title-dulieu xxl:mb-vw-[20] mb-[20px] flex items-center justify-between">
                <p class="xxl:text-vw-[18] mb-0 text-[18px] font-semibold capitalize">Danh sách {{ $config['title_main'] }}
                </p>
                <div class="box-search flex justify-end">
                    <div x-data x-init="$store.globalFunc.keyword = `{{ request()->query('keyword') }}`"
                        class="xxl:border-vw-[1] xxl:rounded-vw-[10] xxl:px-vw-[10px] xxl:py-vw-[5px] flex items-center gap-x-[10px] rounded-[10px] border-[1px] border-solid border-gray-500 px-[10px] py-[5px]">
                        <input type="text" x-model="$store.globalFunc.keyword" @keyup.enter="$store.globalFunc.search()" class="xxl:!text-vw-[14] !text-[14px] text-gray-500 !shadow-none !outline-0 !ring-0"
                            placeholder="Nhập từ khóa tìm kiếm...">
                        @if (request()->query('keyword'))
                            <button type="button" class="xxl:text-vw-[14] inline-block text-[14px]" @click="$store.globalFunc.keyword=``;window.location.href=`{{ request()->url() }}`"><i class="far fa-times"></i></button>
                        @else
                            <button type="button" class="xxl:text-vw-[14] inline-block text-[14px]" @click="$store.globalFunc.search()"><i class="fal fa-search"></i></button>
                        @endif
                    </div>
                </div>
            </div>
            <div class="relative overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-500 rtl:text-right">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-700">
                        <tr class="">
                            <th class="xxl:w-vw-[50] w-[50px] !p-4">
                                <div class="flex items-center">
                                    <label for="check-all-input" class="xxl:gap-x-vw-[3] check-all-input !mb-0 !flex cursor-pointer !items-center gap-x-[3px]">
                                        <input type="checkbox" name="check-all-input" id="check-all-input" x-model="xCheckAll" class="hidden">
                                        <i class="xxl:w-vw-[16] xxl:h-vw-[16px] xxl:rounded-vw-[3] xxl:p-vw-[3] xxl:border-vw-[1] flex h-[16px] w-[16px] items-center justify-center rounded-[3px] border-[1px] p-[3px]"
                                            :class="(xCheckAll) ? 'border-main-1 bg-main-1' : 'border-gray-300 bg-gray-100'">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none" class="hidden w-full fill-none" viewBox="0 0 11 8"
                                                :class="(xCheckAll) ? '!block' : '!hidden'">
                                                <path d="M1 3.82998L3.83 6.65998L9.5 1" class="stroke-white stroke-2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </i>
                                    </label>
                                </div>
                            </th>
                            <th class="xxl:w-vw-[100] w-[100px] !px-4 !py-3 text-center">
                                STT
                            </th>
                            <th class="flex-1 !px-4 !py-3">
                                Tiêu đề
                            </th>
                            @if (!empty($config['options2']))
                                @foreach ($config['options2'] as $k_options => $v_options)
                                    @if (empty($v_options['showindex']))
                                        @continue;
                                    @endif
                                    <th class="xxl:w-vw-[150] w-[150px] !px-4 !py-3">{{ $v_options['title'] }}</th>
                                @endforeach
                            @endif
                            @if ($type == 'thong-bao')
                                <th class="xxl:w-vw-[150] w-[150px] !px-4 !py-3">
                                    Loại thông báo
                                </th>
                            @endif
                            <th class="xxl:w-vw-[250] w-[250px] !px-4 !py-3">
                                Người cập nhật
                            </th>
                            <th class="xxl:w-vw-[200] w-[200px] !px-4 !py-3">
                                Ngày cập nhật
                            </th>
                            @if (request()->userLogin()->hasRole(ADMIN) ||
                                    (request()->userLogin()->hasAnyRole([ADMIN, CS, KETOAN, MANAGER]) &&
                                        $type == 'thong-bao') ||
                                    (request()->userLogin()->hasAnyRole([KETOAN]) &&
                                        in_array($type, ['trang-thai-thanh-toan-khach-hang', 'trang-thai-thanh-toan-ncc', 'trang-thai-thanh-cong-no', 'trang-thai-chi-ho', 'phu-phi-mua', 'phu-phi-ban', 'loai-chi-ho', 'loai-chi-hhkh'])))
                                <th class="xxl:w-vw-[145] w-[145px] !px-4 !py-3 text-center">
                                    Thao tác
                                </th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $v)
                            <tr class="!border-b !border-gray-200 bg-white">
                                <td class="w-4 !p-4">
                                    <div class="flex items-center">
                                        <label for="check-input-{{ $v->id }}" class="xxl:gap-x-vw-[3] check-all-input !mb-0 !flex cursor-pointer !items-center gap-x-[3px]">
                                            <input type="checkbox" value="{{ $v->id }}"
                                                @change="(xCheck.includes(`{{ $v->id }}`)) ? xCheck.splice(xCheck.indexOf(`{{ $v->id }}`), 1) : xCheck.push(`{{ $v->id }}`)"
                                                name="check-input-{{ $v->id }}" id="check-input-{{ $v->id }}" class="hidden">
                                            <i class="xxl:w-vw-[16] xxl:h-vw-[16px] xxl:rounded-vw-[3] xxl:p-vw-[3] xxl:border-vw-[1] flex h-[16px] w-[16px] items-center justify-center rounded-[3px] border-[1px] p-[3px]"
                                                :class="(xCheck.includes(`{{ $v->id }}`)) ? 'border-main-1 bg-main-1' :
                                                'border-gray-300 bg-gray-100'">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none" class="hidden w-full fill-none" viewBox="0 0 11 8"
                                                    :class="((xCheck.includes(`{{ $v->id }}`))) ? '!block' : '!hidden'">
                                                    <path d="M1 3.82998L3.83 6.65998L9.5 1" class="stroke-white stroke-2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </i>
                                        </label>
                                    </div>
                                </td>
                                <td class="!px-4 !py-3">
                                    <p class="xxl:!w-vw-[70] !mb-0 !w-[70px]">
                                        <input x-data @change="$store.globalFunc.changeNumb('news',$event.target.value,{{ $v->id }})" type="number"
                                            class="block w-full cursor-pointer !appearance-auto rounded-lg border border-gray-300 bg-gray-50 !px-2 !py-1 text-center text-sm text-black focus:outline-none"
                                            value="{{ $v->numb }}">
                                    </p>
                                </td>
                                <th scope="row" class="!px-4 !py-3 font-medium text-gray-900">
                                    {{ $v->name }}

                                </th>
                                @if (!empty($config['options2']))
                                    @foreach ($config['options2'] as $k_options => $v_options)
                                        @if (empty($v_options['showindex']))
                                            @continue;
                                        @endif
                                        <td class="!px-4 !py-3">
                                            @if ($k_options == 'color')
                                                <div class="flex w-full items-center">
                                                    <span class="inline-block h-5 w-full rounded-sm text-[0px]" style="background: #{{ $v['options2'][$k_options] }}"></span>
                                                </div>
                                            @elseif($v_options['type-eml'] == 'price')
                                                {{ number_format($v['options2'][$k_options], 2, '.', ',') }}
                                            @else
                                                {{ $v['options2'][$k_options] }}
                                            @endif
                                            </th>
                                    @endforeach
                                @endif
                                @if ($type == 'thong-bao')
                                    <td class="!px-4 !py-3">
                                        @if ($v->noibo == 1 && $v->khachhang == 1)
                                            <span>Thông báo nội bộ và khách hàng</span>
                                        @elseif($v->noibo == 1)
                                            <span>Thông báo nội bộ</span>
                                        @elseif($v->khachhang == 1)
                                            <span>Thông báo khách hàng</span>
                                        @else
                                            <span>Chưa phân loại</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="!px-4 !py-3">
                                    {{ $v->user->fullname ?? '' }}
                                    ({{ \NINACORE\Core\Support\Str::upper($v->user->roles()->first()->name ?? '') }})
                                </td>
                                <td class="!px-4 !py-3">
                                    {{ \Carbon\Carbon::parse($v->updated_at)->format('d/m/Y H:i') }}
                                </td>
                                @if (request()->userLogin()->hasRole(ADMIN) ||
                                        (request()->userLogin()->hasAnyRole([ADMIN, CS, KETOAN, MANAGER]) &&
                                            $type == 'thong-bao') ||
                                        (request()->userLogin()->hasAnyRole([KETOAN]) &&
                                            in_array($type, ['trang-thai-thanh-toan-khach-hang', 'trang-thai-thanh-toan-ncc', 'trang-thai-thanh-cong-no', 'trang-thai-chi-ho', 'phu-phi-mua', 'phu-phi-ban', 'loai-chi-ho', 'loai-chi-hhkh'])))
                                    <td class="xxl:gap-x-vw-[3] flex items-center justify-center gap-x-[3px] !px-4 !py-3">
                                        <a href="{{ url('dulieu', ['type' => $type, 'method' => 'edit'], ['id' => $v->id]) }}" x-tooltip="Cập nhật" class="hover:!text-main-3 text-[0px] font-medium !text-gray-500">
                                            <span class="xxl:w-vw-[24] inline-block w-[24px]">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-pencil">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M4 20h4l10.5 -10.5a2.828 2.828 0 1 0 -4 -4l-10.5 10.5v4" />
                                                    <path d="M13.5 6.5l4 4" />
                                                </svg>
                                            </span>
                                        </a>
                                        @if (
                                            @$config['delete'] &&
                                                request()->userLogin()->hasAnyRole([ADMIN]))
                                            <button type="button" x-tooltip="Xóa dữ liệu" @click="xCheck.push('{{ $v->id }}');deleteData()" class="hover:!text-main-3 !text-[0px] font-medium !text-gray-500">
                                                <span class="xxl:w-vw-[24] inline-block w-[24px]">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                        <path d="M4 7l16 0" />
                                                        <path d="M10 11l0 6" />
                                                        <path d="M14 11l0 6" />
                                                        <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                                                        <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                                    </svg>
                                                </span>
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        @if (!empty($items))
                            @for ($i = 11 - count($items); $i > 0; $i--)
                                <tr class="!border-b !border-gray-200 bg-white">
                                    <td class="!px-6 !py-6"></td>
                                    <td class="!px-6 !py-6"></td>
                                    <td class="!px-6 !py-6"></td>
                                    @if (!empty($config['options2']))
                                        @foreach ($config['options2'] as $k_options => $v_options)
                                            @if (empty($v_options['showindex']))
                                                @continue;
                                            @endif
                                            <td class="!px-6 !py-6"></td>
                                        @endforeach
                                    @endif
                                    @if ($type == 'thong-bao')
                                        <td class="!px-6 !py-6"></td>
                                    @endif
                                    <td class="!px-6 !py-6"></td>
                                    <td class="!px-6 !py-6"></td>
                                    @if (request()->userLogin()->hasRole(ADMIN) ||
                                            (request()->userLogin()->hasAnyRole([ADMIN, CS, KETOAN, MANAGER]) &&
                                                $type == 'thong-bao'))
                                        <td class="!px-6 !py-6"></td>
                                    @endif
                                </tr>
                            @endfor
                        @endif
                    </tbody>
                </table>
            </div>
            @if ($items->hasPages())
                <div class="pagination-blog xxl:mt-vw-[15] mt-[15px] flex justify-end">
                    {!! $items->links('pagination.tailwind') !!}
                </div>
            @endif
        </div>
        @if (request()->userLogin()->hasRole(ADMIN) ||
                (request()->userLogin()->hasAnyRole([ADMIN, CS, KETOAN, MANAGER]) &&
                    $type == 'thong-bao') ||
                (request()->userLogin()->hasAnyRole([KETOAN]) &&
                    in_array($type, ['trang-thai-thanh-toan-khach-hang', 'trang-thai-thanh-toan-ncc', 'trang-thai-thanh-cong-no', 'trang-thai-chi-ho', 'phu-phi-mua', 'phu-phi-ban', 'loai-chi-ho', 'loai-chi-hhkh'])))
            @if (@$config['delete'])
                <div class="header-page-dulieu xxl:p-vw-[10] xxl:mt-vw-[15] xxl:rounded-vw-[10] xxl:gap-x-vw-[10] mt-[15px] flex items-center justify-end gap-x-[10px] rounded-[10px] bg-white p-[10px] shadow-sm">
                    <a class="btn xxl:!rounded-vw-[8] xxl:gap-x-vw-[2] !bg-main-1 hover:!bg-update active:!bg-update !flex items-center justify-center gap-x-[2px] !rounded-[8px] capitalize text-white"
                        href="{{ url($com, ['type' => $type, 'method' => 'add']) }}">
                        <span class="xxl:w-vw-[24] inline-block w-[24px]">
                            <svg class="w-full" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg>
                        </span>
                        thêm dữ liệu
                    </a>
                    @if (request()->userLogin()->hasAnyRole([ADMIN]))
                        <button @click="deleteData()"
                            class="btn xxl:!rounded-vw-[8] xxl:gap-x-vw-[5] !bg-alert/90 hover:!bg-alert active:!bg-alert !flex items-center justify-center gap-x-[5px] !rounded-[8px] capitalize text-white" type="button">
                            <span class="xxl:w-vw-[24] inline-block w-[24px]">
                                <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash-off">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3 3l18 18" />
                                    <path d="M4 7h3m4 0h9" />
                                    <path d="M10 11l0 6" />
                                    <path d="M14 14l0 3" />
                                    <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l.077 -.923" />
                                    <path d="M18.384 14.373l.616 -7.373" />
                                    <path d="M9 5v-1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                                </svg>
                            </span>
                            Xóa dữ liệu
                        </button>
                    @endif
                </div>
            @endif
        @endif
    </div>
    @push('scripts')
        <script>
            function dataTable() {
                return {
                    xCheck: [],
                    xCheckAll: false,
                    init() {
                        this.$watch('xCheckAll', value => {
                            if (value === false) this.xCheck = [];
                            else {
                                document.querySelectorAll('tbody input[type="checkbox"]').forEach(el => {
                                    if (!this.xCheck.includes(el.value)) this.xCheck.push(el.value);
                                });
                            }
                        });
                    },
                    deleteData() {
                        if (this.xCheck.length > 0) {
                            if (confirm('Bạn có chắc chắn muốn xóa những dữ liệu đã chọn?')) {
                                let form = document.createElement("form");
                                form.method = "GET";
                                form.action = "{{ url('dulieu', ['type' => $type, 'method' => 'delete']) }}";
                                let inputData = document.createElement("input");
                                inputData.type = "hidden";
                                inputData.name = "ids";
                                inputData.value = this.xCheck.join(',');
                                form.appendChild(inputData);
                                document.body.appendChild(form);
                                form.submit();
                            }
                        } else {
                            alert('Vui lòng chọn dữ liệu cần xóa!');
                        }
                    }
                }
            }
        </script>
    @endpush
@endsection