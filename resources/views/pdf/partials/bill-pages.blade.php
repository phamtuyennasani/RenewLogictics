{{-- Trang bill của MỘT đơn (kèm CVCK nếu withCvck) — biến: xem OrderPdfRenderer::billData --}}
<table class="b">
    {{-- Header: logo | mã đơn + barcode | thông tin liên hệ --}}
    <tr class="bb">
        <td class="br cell" style="width: 34%; height: 28mm;">
            <div style="font-size: 16pt; font-weight: bold; letter-spacing: .03em; padding-top: 5mm;">{{ config('system.name', 'Bee Express') }}</div>
            <div class="muted" style="font-size: 7.5pt; margin-top: 3px;">International Express &amp; Logistics</div>
        </td>
        <td class="br cell" style="width: 38%;" align="center">
            <div class="muted" style="font-size: 7.5pt; text-transform: uppercase;">Order Code</div>
            <div style="font-size: 15pt; font-weight: bold; letter-spacing: .14em; margin: 2px 0;">{{ $orderCode }}</div>
            <img src="{{ $orderBarcode }}" style="width: 58mm; height: 10mm;" alt="">
        </td>
        <td class="cell" style="font-size: 8pt; line-height: 1.55;">
            <b>Date:</b> {{ $order->created_at?->format('d/m/Y') ?? '-' }}<br>
            <b>Support:</b> {{ config('system.hotline', '+84 987 235514') }}<br>
            <b>Branch:</b> Ho Chi Minh<br>
            <b>Website:</b> {{ config('system.website', 'www.vaupost.vn') }}
        </td>
    </tr>

    <tr class="bb"><td colspan="3" class="cell" style="font-size: 9pt;"><b>Service:</b> {{ $serviceName }}</td></tr>

    {{-- From / To --}}
    <tr class="bb">
        <td colspan="3" style="padding: 0;">
            <table>
                <tr>
                    <td class="br" style="width: 50%; padding: 0;">
                        <div class="head bb">From (Shipper)</div>
                        <table style="font-size: 8pt;">
                            <tr>
                                <td class="sr sb cell" style="width: 34%;">Company name<br><span class="muted">(Contact name)</span></td>
                                <td class="sb cell"><b>{{ data_get($sender, 'company', '-') }}</b><br>{{ data_get($sender, 'fullname', '-') }}</td>
                            </tr>
                            <tr><td class="sr sb cell">Phone/Tax No.</td><td class="sb cell">{{ data_get($sender, 'phone', '-') }}</td></tr>
                            <tr><td class="sr sb cell">Address</td><td class="sb cell">{{ data_get($sender, 'address', '-') }}</td></tr>
                            <tr><td class="sr sb cell">Country</td><td class="sb cell">VIET NAM</td></tr>
                            <tr><td class="sr cell">Email</td><td class="cell">{{ data_get($sender, 'email', '-') }}</td></tr>
                        </table>
                    </td>
                    <td style="padding: 0;">
                        <div class="head bb">To (Consignee)</div>
                        <table style="font-size: 8pt;">
                            <tr>
                                <td class="sr sb cell" style="width: 34%;">Company name<br><span class="muted">(Contact name)</span></td>
                                <td class="sb cell"><b>{{ data_get($receiver, 'company', '-') }}</b><br>{{ data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe', '-')) }}</td>
                            </tr>
                            <tr><td class="sr sb cell">Phone/Fax No.</td><td class="sb cell">{{ data_get($receiver, 'mavung') }} {{ data_get($receiver, 'phone', '-') }}</td></tr>
                            <tr><td class="sr sb cell">Address</td><td class="sb cell">{{ $receiverAddress ?: '-' }}</td></tr>
                            <tr><td class="sr sb cell">Country</td><td class="sb cell">{{ $receiverCountryName }}</td></tr>
                            <tr><td class="sr cell">Postal code</td><td class="cell">{{ data_get($receiver, 'postcode', '-') }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Hàng hóa + giá trị invoice --}}
    <tr class="bb">
        <td colspan="3" style="padding: 0;">
            <table>
                <tr>
                    <td style="width: 70%; padding: 0;">
                        <div class="head sb">Description of goods</div>
                        <div class="cell" style="min-height: 14mm; font-size: 9pt; font-weight: bold;">{{ data_get($order->service ?? [], 'tensanpham', '-') ?: '-' }}</div>
                    </td>
                    <td class="br" style="border-right: 0; border-left: 1px solid #111827; padding: 0;">
                        <div class="head sb">Value invoice</div>
                        <div class="cell" style="min-height: 14mm; font-size: 11pt; font-weight: bold; text-align: center;">{{ number_format($invoiceValue, 2) }} USD</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Tổng kiện / cân --}}
    <tr class="bb">
        <td colspan="3" style="padding: 0;">
            <table style="text-align: center; font-size: 8pt;">
                <tr>
                    <td class="sr cell"><b>Total Packages</b><br><span style="font-size: 12pt; font-weight: bold;">{{ $packageCount }}</span></td>
                    <td class="sr cell"><b>Gross weight</b><br><span style="font-size: 12pt; font-weight: bold;">{{ number_format($grossWeight, 2) }}</span></td>
                    <td class="sr cell"><b>Volume weight</b><br><span style="font-size: 12pt; font-weight: bold;">{{ number_format($volumeWeight, 2) }}</span></td>
                    <td class="cell"><b>Chargeable weight</b><br><span style="font-size: 12pt; font-weight: bold;">{{ number_format($chargeableWeight, 2) }}</span></td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Bảng kiện --}}
    <tr class="bb">
        <td colspan="3" style="padding: 0;">
            <table style="font-size: 8pt;">
                <thead>
                    <tr>
                        <th class="sb sr cell" style="text-align: center;">Package</th>
                        <th class="sb sr cell" style="text-align: center;">L (cm)</th>
                        <th class="sb sr cell" style="text-align: center;">W (cm)</th>
                        <th class="sb sr cell" style="text-align: center;">H (cm)</th>
                        <th class="sb cell" style="text-align: center;">Code</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->packages as $package)
                        <tr>
                            <td class="sb sr cell" style="text-align: center;">1</td>
                            <td class="sb sr cell" style="text-align: center;">{{ $package->length ?: '-' }}</td>
                            <td class="sb sr cell" style="text-align: center;">{{ $package->width ?: '-' }}</td>
                            <td class="sb sr cell" style="text-align: center;">{{ $package->height ?: '-' }}</td>
                            <td class="sb cell" style="text-align: center;">{{ $package->code ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="cell" style="text-align: center;">No packages</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>

    {{-- Chữ ký --}}
    <tr>
        <td colspan="3" style="padding: 0;">
            <table>
                <tr>
                    <td class="br cell" style="width: 50%; height: 30mm;">
                        <b>Chữ ký người gửi (Shipper Signature)</b>
                        <div style="margin-top: 8px;">Ngày, giờ (Date/time): ...............................................</div>
                    </td>
                    <td class="cell">
                        <b>Nhân viên nhận hàng (Picked up by)</b>
                        <div style="margin-top: 8px;">Ngày, giờ (Date/time): ...............................................</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="b" style="margin-top: 8px;">
    <tr><td class="cell" style="font-size: 7.5pt; line-height: 1.45;">
        <b>Lưu ý / Notes:</b> Khách hàng xác nhận thông tin trên bill là chính xác và đồng ý với chính sách vận chuyển, khai báo hàng hóa, bồi thường và xử lý phát sinh của {{ config('system.name', 'Bee Express') }}.
    </td></tr>
</table>

@if($withCvck)
    <div class="page-break" style="font-family: 'DejaVu Serif', serif; font-size: 10pt; line-height: 1.55; padding: 6mm 4mm;">
        <div style="text-align: center; line-height: 1.45;">
            <div style="font-weight: bold;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
            <div>Độc Lập - Tự Do - Hạnh Phúc</div>
            <div>---***---</div>
            <div style="margin-top: 12px; font-weight: bold; font-size: 12pt;">CÔNG VĂN CAM KẾT NỘI DUNG HÀNG XUẤT</div>
            <div style="font-weight: bold;">LETTER OF GUARANTEE</div>
            <div>TP.HCM ngày {{ now()->format('d') }} tháng {{ now()->format('m') }} năm {{ now()->format('Y') }}</div>
        </div>

        <div style="margin-top: 14px;">
            <p style="margin: 0;">Kính gửi: - Chi cục Hải Quan cửa khẩu Tân Sơn Nhất</p>
            <p style="margin: 0 0 0 44px;">- Ban soi chiếu an ninh hàng không Tân Sơn Nhất</p>
            <p style="margin: 0 0 0 44px;">- Công ty TNHH dịch vụ hàng hóa Tân Sơn Nhất (TCS/TECS)</p>
            <p style="margin: 0 0 0 44px;">- Công ty TNHH dịch vụ hàng hóa Sài Gòn (SCSC)</p>
            <p style="margin: 0 0 0 44px;">- Công Ty TNHH Xuất Nhập Khẩu {{ config('system.name', 'Bee Express') }}</p>
        </div>

        <div style="margin-top: 14px;">
            <p style="margin: 0 0 4px;">Chúng tôi là / <i>We're</i>: <b>{{ $cvckName }}</b></p>
            <p style="margin: 0 0 4px;">MST / CMND số: <b>{{ $cvckId }}</b></p>
            <p style="margin: 0 0 4px;">Địa chỉ / <i>Address</i>: <b>{{ $cvckAddress }}</b></p>
            <p style="margin: 0 0 4px;">Có gửi đến / <i>Shipment send to</i>: <b>{{ $receiverAddress ?: '-' }}</b></p>
            <p style="margin: 0 0 4px;">Số bill / <i>Consignment note No.</i>: <b>{{ $orderCode }}</b></p>
            <p style="margin: 0 0 4px;">Nội dung hàng gửi gồm / <i>Content</i>: <b>{{ data_get($order->service ?? [], 'tensanpham', '-') ?: '-' }}</b></p>
            <p style="margin: 0 0 4px 20px;">Số kiện / <i>No pcs</i>: <b>{{ $packageCount }}</b></p>
            <p style="margin: 0 0 4px 20px;">Trọng lượng thực tế / <i>Gross weight</i>: <b>{{ number_format($grossWeight, 2) }} KG</b></p>
        </div>

        <table style="margin-top: 8px; font-size: 9.5pt;">
            <thead>
                <tr>
                    <th style="border: 1px solid #000; padding: 4px; width: 12%;">STT</th>
                    <th style="border: 1px solid #000; padding: 4px;">TÊN HÀNG</th>
                    <th style="border: 1px solid #000; padding: 4px; width: 18%;">SỐ LƯỢNG</th>
                    <th style="border: 1px solid #000; padding: 4px; width: 20%;">VALUE (USD)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->invoices as $invoiceIndex => $invoice)
                    <tr>
                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;">{{ $invoiceIndex + 1 }}</td>
                        <td style="border: 1px solid #000; padding: 4px;">{!! nl2br(e($invoice->tenhang ?: '-')) !!}</td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;">{{ $invoice->soluong ?: '-' }}</td>
                        <td style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;">{{ number_format((float) $invoice->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="border: 1px solid #000; padding: 8px; text-align: center;">Không có invoice</td></tr>
                @endforelse
                <tr>
                    <td colspan="2" style="border: 0;"></td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;">{{ $invoiceQty }}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-weight: bold;">{{ number_format($invoiceValue, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top: 12px;">Chúng tôi xin cam kết lô hàng này không phải là hàng nguy hiểm, độc hại, không chứa xăng dầu, khí gas, khí nén, từ tính, không phải hàng dễ cháy nổ, không có tiền chất ma túy, không chứa ma túy, không phải hàng quốc cấm, hàng cấm xuất khẩu. Chúng tôi cam kết hàng đúng như khai báo và chịu trách nhiệm về nội dung lô hàng xuất nói trên.</p>
        <p>Vậy kính mong quý cơ quan tạo điều kiện cho lô hàng được xuất đi trong thời gian sớm nhất. Tôi cam đoan toàn bộ thông tin trong công văn này là đúng sự thật.</p>
        <p>Trân trọng. <i>Best regards</i>,</p>

        <div style="margin-top: 20px; width: 240px; text-align: center;">
            <p style="margin: 0; font-weight: bold;">Giám đốc / <i style="font-weight: normal;">Director</i></p>
            <p style="margin: 0;">(Ký, đóng dấu ghi rõ họ tên)</p>
        </div>
    </div>
@endif
