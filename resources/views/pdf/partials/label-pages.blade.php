{{-- Các trang tem của MỘT đơn — biến: order, orderCode, sender, receiver, labelPages, totalLabelPages, barcodes, serviceName --}}
@foreach($labelPages as $labelIndex => $label)
    @php
        $package = $label['package'];
        $receiverName = data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe', ''));
        $receiverLocation = trim(implode(' ', array_filter([
            data_get($receiver, 'city'),
            data_get($receiver, 'state'),
            data_get($receiver, 'postcode'),
        ])));
        $senderName = data_get($sender, 'company') ?: data_get($sender, 'fullname', '-');
        $dimensions = $package
            ? trim(($package->length ?: '-') . ' x ' . ($package->width ?: '-') . ' x ' . ($package->height ?: '-') . ' cm')
            : '-';
        $packageCode = (string) $label['package_code'];
    @endphp
    <div class="page">
        {{-- Header: mã đơn + số kiện --}}
        <table style="border-bottom: 2px solid #111827;">
            <tr>
                <td style="padding-bottom: 5px;">
                    <div class="lbl">Shipping Label</div>
                    <div style="font-size: 17pt; font-weight: bold; line-height: 1.1;">{{ $orderCode }}</div>
                </td>
                <td style="width: 26mm; padding-bottom: 5px;" align="right">
                    <table style="width: 24mm; border: 1px solid #111827;">
                        <tr><td align="center" style="padding: 2px;"><span class="lbl">Kiện</span><br>
                            <span style="font-size: 14pt; font-weight: bold;">{{ $labelIndex + 1 }}/{{ $totalLabelPages }}</span>
                        </td></tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Barcode kiện --}}
        <table style="border-bottom: 1px solid #111827;">
            <tr><td align="center" style="padding: 5px 0 3px;">
                <img src="{{ $barcodes[$packageCode] }}" style="width: 86mm; height: 12mm;" alt="">
                <div style="font-size: 11pt; font-weight: bold; letter-spacing: 1px; margin-top: 1px;">{{ $packageCode }}</div>
            </td></tr>
        </table>

        {{-- Người nhận --}}
        <div class="box" style="margin-top: 6px;">
            <div class="lbl">Người nhận / Consignee</div>
            <div style="font-size: 13pt; font-weight: bold; line-height: 1.15; margin-top: 1px;">{{ $receiverName ?: data_get($receiver, 'company', '-') }}</div>
            @if(data_get($receiver, 'company') && $receiverName)
                <div style="font-size: 8.5pt; font-weight: bold;">{{ data_get($receiver, 'company') }}</div>
            @endif
            <div style="font-size: 8.5pt; line-height: 1.35; margin-top: 2px;">{{ data_get($receiver, 'address', '-') }}</div>
            <div style="font-size: 8.5pt; line-height: 1.35;">{{ $receiverLocation ?: '-' }}</div>
            <div style="font-size: 9.5pt; font-weight: bold; margin-top: 2px;">Tel: {{ data_get($receiver, 'mavung') }} {{ data_get($receiver, 'phone', '-') }}</div>
        </div>

        {{-- Cân nặng / kích thước / dịch vụ --}}
        <table style="margin-top: 5px;">
            <tr>
                <td class="box-soft" style="width: 50%;">
                    <span class="lbl">Cân nặng</span><br>
                    <span style="font-size: 9.5pt; font-weight: bold;">{{ $package?->c_weight ?? '-' }} kg</span>
                </td>
                <td style="width: 4px;"></td>
                <td class="box-soft">
                    <span class="lbl">Kích thước</span><br>
                    <span style="font-size: 9.5pt; font-weight: bold;">{{ $dimensions }}</span>
                </td>
            </tr>
        </table>
        <div class="box-soft" style="margin-top: 4px;">
            <span class="lbl">Dịch vụ</span><br>
            <span style="font-size: 9.5pt; font-weight: bold;">{{ $serviceName }}</span>
        </div>

        {{-- Hàng hóa --}}
        <div class="box-soft" style="margin-top: 4px;">
            <span class="lbl">Hàng hóa / Description</span><br>
            <span style="font-size: 10pt; font-weight: bold;">{{ data_get($order->service ?? [], 'tensanpham', '-') ?: '-' }}</span>
        </div>

        {{-- Người gửi --}}
        <div class="box-soft" style="margin-top: 4px;">
            <span class="lbl">Người gửi / Shipper</span><br>
            <span style="font-size: 8.5pt; font-weight: bold;">{{ $senderName }}</span><br>
            <span style="font-size: 8pt; line-height: 1.3;">{{ data_get($sender, 'address', '-') }}</span><br>
            <span style="font-size: 8pt; font-weight: bold;">Tel: {{ data_get($sender, 'phone', '-') }}</span>
        </div>

        {{-- Footer --}}
        <table style="margin-top: 5px; border-top: 1px dashed #9ca3af;">
            <tr>
                <td class="muted" style="font-size: 7.5pt; padding-top: 3px;">Tạo lúc: {{ $order->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                <td class="muted" style="font-size: 7.5pt; padding-top: 3px;" align="right">Dòng kiện {{ $label['package_index'] }}: {{ $label['piece'] }}/{{ $label['pieces_in_row'] }}</td>
            </tr>
        </table>
    </div>
@endforeach
