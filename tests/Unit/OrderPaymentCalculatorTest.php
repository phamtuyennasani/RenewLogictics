<?php

namespace Tests\Unit;

use App\Support\OrderPaymentCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Chốt chặn số liệu cho OrderPaymentCalculator — nguồn sự thật duy nhất
 * của tính toán payment (màn tạo đơn + màn cập nhật giá).
 *
 * Các case số lấy đúng ví dụ trong docs/payment-calculation-guide.md.
 */
class OrderPaymentCalculatorTest extends TestCase
{
    // ------------------------------------------------------------------
    // parseNumber — docs/payment-calculation-guide.md mục 7
    // ------------------------------------------------------------------

    public function test_parse_number_handles_vietnamese_and_english_formats(): void
    {
        // "1.000.000" không được thành 1 (bug định dạng VN kinh điển).
        $this->assertSame(1000000.0, OrderPaymentCalculator::parseNumber('1.000.000'));
        $this->assertSame(1000000.0, OrderPaymentCalculator::parseNumber('1,000,000'));
        $this->assertSame(1000000.0, OrderPaymentCalculator::parseNumber('1000000'));
        $this->assertSame(1000000.0, OrderPaymentCalculator::parseNumber(1000000));
        $this->assertSame(1000.5, OrderPaymentCalculator::parseNumber('1,000.50'));
        $this->assertSame(1000.5, OrderPaymentCalculator::parseNumber('1.000,50'));
        $this->assertSame(0.0, OrderPaymentCalculator::parseNumber(''));
        $this->assertSame(0.0, OrderPaymentCalculator::parseNumber(null));
        $this->assertSame(0.0, OrderPaymentCalculator::parseNumber('abc'));
        $this->assertSame(12.5, OrderPaymentCalculator::parseNumber('12.5'));
        $this->assertSame(-500.0, OrderPaymentCalculator::parseNumber('-500'));
    }

    // ------------------------------------------------------------------
    // recalculateGroup — cước chính + PPXD + VAT (guide mục 2)
    // ------------------------------------------------------------------

    public function test_group_totals_with_ppxd_and_vat(): void
    {
        $group = OrderPaymentCalculator::recalculateGroup(
            OrderPaymentCalculator::normalizeGroup([
                'dongiaban' => 10_000_000,
                'ppxd_percent' => 10,
                'vat_percent' => 8,
            ], 'dongiaban'),
            'dongiaban',
            ['phuphi', 'hh_khachhang'],
        );

        // ppxd = 10tr × 10% = 1tr; vat = (10tr + 1tr) × 8% = 880k.
        $this->assertSame(1_000_000.0, $group['ppxd_amount']);
        $this->assertSame(880_000.0, $group['vat_amount']);
        $this->assertSame(11_880_000.0, $group['tongcuoc']);
        $this->assertSame(11_000_000.0, $group['total_tongcuoc_no_vat']);
        $this->assertSame(11_880_000.0, $group['total_tongcuoc']);
    }

    public function test_fee_rows_compute_per_row_vat_and_totals(): void
    {
        $group = OrderPaymentCalculator::recalculateGroup(
            OrderPaymentCalculator::normalizeGroup([
                'dongiaban' => 1_000_000,
                'phuphi' => [
                    ['soluong' => 2, 'price' => 100_000, 'vat_percent' => 10],
                    ['soluong' => 1, 'price' => 50_000],
                ],
            ], 'dongiaban'),
            'dongiaban',
            ['phuphi', 'hh_khachhang'],
        );

        // Dòng 1: total 200k, vat 20k, sau VAT 220k. Dòng 2: 50k, không VAT.
        $this->assertSame(200_000.0, $group['phuphi'][0]['total']);
        $this->assertSame(20_000.0, $group['phuphi'][0]['vat_amount']);
        $this->assertSame(220_000.0, $group['phuphi'][0]['total_after_vat']);
        $this->assertSame(250_000.0, $group['total_phuphi_no_vat']);
        $this->assertSame(20_000.0, $group['total_vat_phuphi']);
        // total_phuphi theo nghĩa SAU VAT (thống nhất với màn payment).
        $this->assertSame(270_000.0, $group['total_phuphi']);
        // Tổng trước VAT = cước chính + phụ phí trước VAT.
        $this->assertSame(1_250_000.0, $group['total_tongcuoc_no_vat']);
        // Tổng sau VAT = tongcuoc + phụ phí trước VAT + VAT phụ phí.
        $this->assertSame(1_270_000.0, $group['total_tongcuoc']);
    }

    public function test_phichiho_rows_are_computed_but_excluded_from_totals(): void
    {
        $group = OrderPaymentCalculator::recalculateGroup(
            OrderPaymentCalculator::normalizeGroup([
                'dongiavon' => 1_000_000,
                'phichiho' => [
                    ['soluong' => 1, 'price' => 300_000],
                ],
            ], 'dongiavon'),
            'dongiavon',
            ['phuphi', 'phichiho'],
            ['phichiho'],
        );

        // Chi hộ được tính từng dòng + total riêng...
        $this->assertSame(300_000.0, $group['phichiho'][0]['total']);
        $this->assertSame(300_000.0, $group['total_phichiho']);
        // ...nhưng KHÔNG cộng vào tổng cước vốn.
        $this->assertSame(1_000_000.0, $group['total_tongcuoc_no_vat']);
        $this->assertSame(1_000_000.0, $group['total_tongcuoc']);
        $this->assertEquals(0, $group['total_phuphi']);
    }

    public function test_hh_khachhang_rows_have_no_vat_and_sum_into_commission(): void
    {
        $group = OrderPaymentCalculator::recalculateGroup(
            OrderPaymentCalculator::normalizeGroup([
                'dongiaban' => 10_000_000,
                'hh_khachhang' => [
                    ['so_tien' => 300_000, 'vat_percent' => 10],
                    ['so_tien' => 200_000],
                ],
            ], 'dongiaban'),
            'dongiaban',
            ['phuphi', 'hh_khachhang'],
        );

        $this->assertEquals(0, $group['hh_khachhang'][0]['vat_amount']);
        $this->assertSame(300_000.0, $group['hh_khachhang'][0]['total']);
        $this->assertSame(500_000.0, $group['total_hh_khachhang']);
        // Hoa hồng KHÔNG cộng vào tổng cước (trừ khi tính lợi nhuận).
        $this->assertSame(10_000_000.0, $group['total_tongcuoc_no_vat']);
    }

    // ------------------------------------------------------------------
    // recalculateAll — bonus sale (guide mục 3)
    // ------------------------------------------------------------------

    public function test_bonus_sale_follows_guide_example(): void
    {
        // Guide: cuocban no VAT 10tr, bonus 5% → 500k.
        $payment = OrderPaymentCalculator::recalculateAll([
            'cuocban' => ['dongiaban' => 10_000_000],
            'cuocvon' => ['dongiavon' => 7_000_000, 'bonus_sale_percent' => 5],
            'cuocgoc' => ['dongiagoc' => 6_000_000],
        ]);

        $this->assertSame(500_000.0, $payment['cuocvon']['bonus_sale_amount']);
    }

    // ------------------------------------------------------------------
    // profitSnapshot — guide mục 5–6
    // ------------------------------------------------------------------

    public function test_profit_snapshot_follows_guide_example(): void
    {
        // Guide 5.2/5.3: 10tr − 7tr − 500k hh = 2.5tr tạm tính; − 500k bonus = 2tr.
        $payment = OrderPaymentCalculator::recalculateAll([
            'cuocban' => [
                'dongiaban' => 10_000_000,
                'hh_khachhang' => [['so_tien' => 500_000]],
            ],
            'cuocvon' => ['dongiavon' => 7_000_000, 'bonus_sale_percent' => 5],
            'cuocgoc' => ['dongiagoc' => 6_000_000],
        ]);

        $profit = OrderPaymentCalculator::profitSnapshot($payment);

        $this->assertSame(2_500_000.0, $profit['loinhuantamtinh']);
        $this->assertSame(2_000_000.0, $profit['loinhuan']);
        // Tỷ suất: 2.5tr/10tr = 25%; 2tr/10tr = 20%.
        $this->assertSame(25.0, $profit['tysuattamtinh']);
        $this->assertSame(20.0, $profit['tysuat']);
        $this->assertSame(20.0, $profit['tysuatloinhuan']);
    }

    public function test_profit_rate_example_15_25_percent(): void
    {
        // Guide mục 6: loinhuan 1,525,000 / cuocban_no_vat 10,000,000 = 15.25%.
        $profit = OrderPaymentCalculator::profitSnapshot([
            'cuocban' => ['total_tongcuoc_no_vat' => 10_000_000, 'total_tongcuoc' => 10_000_000],
            'cuocvon' => ['total_tongcuoc_no_vat' => 8_475_000, 'total_tongcuoc' => 8_475_000, 'bonus_sale_amount' => 0],
            'cuocgoc' => [],
        ]);

        $this->assertSame(1_525_000.0, $profit['loinhuan']);
        $this->assertSame(15.25, $profit['tysuat']);
    }

    public function test_profit_rates_are_zero_when_sale_total_is_zero(): void
    {
        $profit = OrderPaymentCalculator::profitSnapshot([
            'cuocban' => [],
            'cuocvon' => [],
            'cuocgoc' => [],
        ]);

        $this->assertSame(0, $profit['tysuattamtinh']);
        $this->assertSame(0, $profit['tysuat']);
    }

    // ------------------------------------------------------------------
    // Parity create ↔ payment: bất động điểm
    // ------------------------------------------------------------------

    public function test_recalculate_all_is_idempotent(): void
    {
        // Payload như màn tạo đơn sinh ra, chạy qua calculator lần 1 (create),
        // rồi lần 2 (mở màn payment bấm recalc) → số KHÔNG được đổi.
        // Đây là bằng chứng 2 màn hình không thể lệch nhau.
        $first = OrderPaymentCalculator::recalculateAll([
            'cuocban' => [
                'dongiaban' => 4_350_000,
                'vat_percent' => 8,
                'ppxd_percent' => 10,
                'phuphi' => [
                    ['soluong' => 2, 'price' => 150_000, 'vat_percent' => 10],
                    ['soluong' => 1, 'price' => 80_000],
                ],
                'hh_khachhang' => [['so_tien' => 200_000]],
            ],
            'cuocvon' => [
                'dongiavon' => 3_100_000,
                'bonus_sale_percent' => 5,
                'phuphi' => [['soluong' => 1, 'price' => 50_000]],
                'phichiho' => [['soluong' => 1, 'price' => 400_000]],
            ],
            'cuocgoc' => ['dongiagoc' => 2_800_000],
        ]);
        $first['payment_loinhuan'] = OrderPaymentCalculator::profitSnapshot($first);

        $second = OrderPaymentCalculator::recalculateAll($first);
        $second['payment_loinhuan'] = OrderPaymentCalculator::profitSnapshot($second);

        $this->assertSame($first, $second);
    }

    public function test_metadata_keys_survive_recalculation(): void
    {
        // Metadata service_price_* của màn tạo đơn phải sống sót qua recalc
        // ở màn payment (không bị normalizeGroup xóa).
        $payment = OrderPaymentCalculator::recalculateAll([
            'cuocban' => [
                'dongiaban' => 1_000_000,
                'service_price_list_id' => 7,
                'service_price_quycach' => 'DON_GIA',
            ],
            'cuocvon' => [],
            'cuocgoc' => [],
        ]);

        $this->assertSame(7, $payment['cuocban']['service_price_list_id']);
        $this->assertSame('DON_GIA', $payment['cuocban']['service_price_quycach']);
    }
}
