<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hóa đơn điện tử</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f7; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f4f7;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden;">

                    {{-- HEADER --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #3b82f6, #0ea5e9); padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700;">
                                {{ $company['short_name'] ?: $company['name'] }}
                            </h1>
                            @if($company['address'])
                                <p style="margin: 8px 0 0; color: rgba(255,255,255,0.9); font-size: 13px;">
                                    {{ $company['address'] }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    {{-- BODY --}}
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 20px; color: #1f2937; font-size: 16px;">
                                Kính gửi <strong>{{ $customerName }}</strong>,
                            </p>

                            <p style="margin: 0 0 24px; color: #4b5563; font-size: 15px;">
                                Cảm ơn Quý khách đã sử dụng dịch vụ của chúng tôi. Chúng tôi xin gửi đến Quý khách hóa đơn điện tử cho đơn hàng vừa thanh toán.
                            </p>

                            {{-- Invoice Info Box --}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                                    <span style="color: #6b7280; font-size: 13px;">Số hóa đơn</span>
                                                    <p style="margin: 4px 0 0; color: #059669; font-size: 18px; font-weight: 700;">
                                                        {{ $einvoice->invoice_number ?: $einvoice->reference }}
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                                                    <span style="color: #6b7280; font-size: 13px;">Mã đơn hàng</span>
                                                    <p style="margin: 4px 0 0; color: #1f2937; font-size: 15px; font-weight: 600;">
                                                        {{ $orderCode }}
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid #e2e8f0;">
                                                    <span style="color: #6b7280; font-size: 13px;">Ngày phát hành</span>
                                                    <p style="margin: 4px 0 0; color: #1f2937; font-size: 15px; font-weight: 600;">
                                                        {{ $einvoice->issued_at?->format('d/m/Y H:i') ?: ($einvoice->issued_date?->format('d/m/Y') ?: '-') }}
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0;">
                                                    <span style="color: #6b7280; font-size: 13px;">Tổng tiền thanh toán</span>
                                                    <p style="margin: 4px 0 0; color: #dc2626; font-size: 20px; font-weight: 700;">
                                                        {{ number_format((float) $einvoice->amount, 0, ',', '.') }} đ
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Attachment note --}}
                            <p style="margin: 0 0 24px; color: #4b5563; font-size: 15px;">
                                📎 <strong>File hóa đơn điện tử (PDF)</strong> được đính kèm trong email này. Quý khách vui lòng tải về và lưu trữ để sử dụng khi cần.
                            </p>

                            {{-- CTA --}}
                            @if($einvoice->invoice_url)
                                <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 auto 24px;">
                                    <tr>
                                        <td style="background: linear-gradient(135deg, #3b82f6, #0ea5e9); border-radius: 6px;">
                                            <a href="{{ $einvoice->invoice_url }}" target="_blank" style="display: inline-block; padding: 14px 28px; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600;">
                                                Xem hóa đơn trực tuyến →
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            {{-- Closing --}}
                            <p style="margin: 0 0 8px; color: #4b5563; font-size: 15px;">
                                Nếu có bất kỳ thắc mắc nào, Quý khách vui lòng liên hệ với chúng tôi qua:
                            </p>
                            <ul style="margin: 0 0 24px; padding-left: 20px; color: #4b5563; font-size: 14px;">
                                @if($company['phone'])
                                    <li style="margin-bottom: 4px;">Điện thoại: <strong>{{ $company['phone'] }}</strong></li>
                                @endif
                                @if($company['email'])
                                    <li style="margin-bottom: 4px;">Email: <strong>{{ $company['email'] }}</strong></li>
                                @endif
                                @if($company['website'])
                                    <li>Website: <a href="{{ $company['website'] }}" style="color: #3b82f6;">{{ $company['website'] }}</a></li>
                                @endif
                            </ul>

                            <p style="margin: 0; color: #1f2937; font-size: 15px;">
                                Trân trọng,<br>
                                <strong>{{ $company['name'] }}</strong>
                            </p>
                        </td>
                    </tr>

                    {{-- FOOTER --}}
                    <tr>
                        <td style="background-color: #f8fafc; padding: 24px 40px; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 8px; color: #6b7280; font-size: 12px; text-align: center;">
                                Email này được gửi tự động từ hệ thống. Vui lòng không trả lời trực tiếp.
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 11px; text-align: center;">
                                © {{ date('Y') }} {{ $company['name'] }}. All rights reserved.
                                @if($company['tax_code'])
                                    <br>MST: {{ $company['tax_code'] }}
                                @endif
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
