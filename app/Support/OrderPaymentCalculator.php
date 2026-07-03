<?php

namespace App\Support;

/**
 * Nguồn sự thật duy nhất cho tính toán payment đơn hàng.
 *
 * Cả màn tạo đơn (CreateOrderAction) và màn cập nhật giá
 * (pages/order/⚡payment.blade.php) đều phải tính qua class này —
 * không nhân bản công thức ra chỗ khác.
 *
 * Công thức theo docs/payment-calculation-guide.md và
 * docs/SERVICE_PRICE_CALCULATION.md:
 * - ppxd_amount   = cước chính × ppxd%
 * - vat_amount    = (cước chính + ppxd) × vat%
 * - tongcuoc      = cước chính + ppxd + vat
 * - từng dòng phụ phí: total = soluong × price; vat riêng theo dòng
 * - total_tongcuoc_no_vat = cước chính + ppxd + tổng phụ phí trước VAT
 * - total_tongcuoc        = tongcuoc + tổng phụ phí sau VAT
 * - bonus sale    = cuocban.total_tongcuoc_no_vat × bonus%
 * - lợi nhuận     = cuocban_no_vat − cuocvon_no_vat − hoa hồng KH − bonus sale
 */
class OrderPaymentCalculator
{
    /**
     * Bucket phụ phí theo nhóm cước. `phichiho` (chi hộ) được tính từng dòng
     * nhưng KHÔNG cộng vào tổng cước vốn; `hh_khachhang` là tiền hoa hồng,
     * không có VAT dòng.
     */
    public const GROUP_BUCKETS = [
        'cuocban' => ['buckets' => ['phuphi', 'hh_khachhang'], 'excluded' => []],
        'cuocvon' => ['buckets' => ['phuphi', 'phichiho'], 'excluded' => ['phichiho']],
        'cuocgoc' => ['buckets' => ['phuphi'], 'excluded' => []],
    ];

    public const PRICE_KEYS = [
        'cuocban' => 'dongiaban',
        'cuocvon' => 'dongiavon',
        'cuocgoc' => 'dongiagoc',
    ];

    /**
     * Parser số dùng chung: chấp nhận "1.000.000", "1,000,000", "1000000",
     * "1,000.50", "1.000,50". KHÔNG dùng str_replace thô ở call site.
     */
    public static function parseNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.,-]/', '', (string) $value);

        if ($normalized === '' || $normalized === '-') {
            return 0;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $normalized)) {
                $normalized = str_replace(',', '', $normalized);
            } else {
                $normalized = str_replace(',', '.', $normalized);
            }
        } elseif ($hasDot && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }

    /**
     * Khung mặc định đủ key cho 1 nhóm cước. Key lạ (metadata service_price_*)
     * được giữ nguyên khi merge.
     */
    public static function normalizeGroup(?array $group, string $priceKey): array
    {
        return array_merge([
            $priceKey => 0,
            'vat_percent' => 0,
            'vat_amount' => 0,
            'ppxd_percent' => 0,
            'ppxd_amount' => 0,
            'tongcuoc' => 0,
            'phuphi' => [],
            'phichiho' => [],
            'hh_khachhang' => [],
            'bonus_sale_percent' => 0,
            'bonus_sale_amount' => 0,
            'total_hh_khachhang' => 0,
            'total_vat' => 0,
            'total_vat_phuphi' => 0,
            'total_phuphi_no_vat' => 0,
            'total_phuphi' => 0,
            'total_tongcuoc_no_vat' => 0,
            'total_tongcuoc' => 0,
        ], $group ?? []);
    }

    /**
     * Tính lại 1 nhóm cước: cước chính + PPXD + VAT + từng dòng bucket.
     * $excludedBuckets: bucket được tính từng dòng nhưng không cộng vào tổng.
     */
    public static function recalculateGroup(array $group, string $priceKey, array $buckets, array $excludedBuckets = []): array
    {
        $price = self::parseNumber($group[$priceKey] ?? 0);
        $vatPercent = self::parseNumber($group['vat_percent'] ?? 0);
        $ppxdPercent = self::parseNumber($group['ppxd_percent'] ?? 0);
        $ppxdAmount = round($price * $ppxdPercent / 100);
        $vatAmount = round(($price + $ppxdAmount) * $vatPercent / 100);
        $tongcuoc = $price + $ppxdAmount + $vatAmount;
        $feeTotalNoVat = 0;
        $feeVatTotal = 0;
        $feeTotal = 0;

        foreach ($buckets as $bucket) {
            $rows = $group[$bucket] ?? [];

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    $row = [];
                    $rows[$index] = $row;
                }

                if ($bucket === 'hh_khachhang') {
                    $amount = self::parseNumber($row['so_tien'] ?? ($row['price'] ?? 0));
                    $rows[$index]['total'] = $amount;
                    $rows[$index]['total_after_vat'] = $amount;
                    $rows[$index]['vat_amount'] = 0;

                    continue;
                }

                $qty = max(0, self::parseNumber($row['soluong'] ?? 0));
                $rowPrice = self::parseNumber($row['price'] ?? ($row['so_tien'] ?? 0));
                $rowVatPercent = self::parseNumber($row['vat_percent'] ?? ($row['vat'] ?? 0));
                $rowTotal = round(($qty ?: 1) * $rowPrice);
                $rowVatAmount = round($rowTotal * $rowVatPercent / 100);
                $rows[$index]['vat_amount'] = $rowVatAmount;
                $rows[$index]['total'] = $rowTotal;
                $rows[$index]['total_after_vat'] = $rowTotal + $rowVatAmount;

                if (in_array($bucket, $excludedBuckets, true)) {
                    continue;
                }

                $feeTotalNoVat += $rowTotal;
                $feeVatTotal += $rowVatAmount;
                $feeTotal += $rows[$index]['total_after_vat'];
            }

            $group[$bucket] = array_values($rows);
        }

        $group[$priceKey] = $price;
        $group['ppxd_amount'] = $ppxdAmount;
        $group['vat_amount'] = $vatAmount;
        $group['total_vat'] = $vatAmount + $feeVatTotal;
        $group['total_vat_phuphi'] = $feeVatTotal;
        $group['total_phuphi_no_vat'] = $feeTotalNoVat;
        $group['total_phuphi'] = $feeTotal;
        $group['total_phichiho'] = array_sum(array_map(
            fn ($row) => self::parseNumber(is_array($row) ? ($row['price'] ?? ($row['so_tien'] ?? 0)) : 0),
            $group['phichiho'] ?? []
        ));
        $group['total_hh_khachhang'] = array_sum(array_map(
            fn ($row) => self::parseNumber(is_array($row) ? ($row['so_tien'] ?? ($row['price'] ?? 0)) : 0),
            $group['hh_khachhang'] ?? []
        ));
        $group['total_tongcuoc_no_vat'] = $price + $ppxdAmount + $feeTotalNoVat;
        $group['tongcuoc'] = $tongcuoc;
        $group['total_tongcuoc'] = $tongcuoc + $feeVatTotal + $feeTotalNoVat;

        return $group;
    }

    /**
     * Tính lại cả 3 nhóm + bonus sale.
     * Bonus percent đọc từ cuocvon.bonus_sale_percent — quyền ai được sửa
     * percent là việc của UI, calculator chỉ tính.
     */
    public static function recalculateAll(array $payment): array
    {
        foreach (self::GROUP_BUCKETS as $groupKey => $config) {
            $payment[$groupKey] = self::recalculateGroup(
                self::normalizeGroup($payment[$groupKey] ?? null, self::PRICE_KEYS[$groupKey]),
                self::PRICE_KEYS[$groupKey],
                $config['buckets'],
                $config['excluded'],
            );
        }

        $saleTotalNoVat = self::parseNumber($payment['cuocban']['total_tongcuoc_no_vat'] ?? 0);
        $bonusPercent = self::parseNumber($payment['cuocvon']['bonus_sale_percent'] ?? 0);
        $payment['cuocvon']['bonus_sale_percent'] = $bonusPercent;
        $payment['cuocvon']['bonus_sale_amount'] = round($saleTotalNoVat * ($bonusPercent / 100));

        return $payment;
    }

    /**
     * Snapshot lợi nhuận từ 3 nhóm đã tính (mục 5–6 payment-calculation-guide.md).
     */
    public static function profitSnapshot(array $payment): array
    {
        $saleNoVat = self::parseNumber($payment['cuocban']['total_tongcuoc_no_vat'] ?? 0);
        $sale = self::parseNumber($payment['cuocban']['total_tongcuoc'] ?? 0);
        $costNoVat = self::parseNumber($payment['cuocvon']['total_tongcuoc_no_vat'] ?? 0);
        $cost = self::parseNumber($payment['cuocvon']['total_tongcuoc'] ?? 0);
        $baseNoVat = self::parseNumber($payment['cuocgoc']['total_tongcuoc_no_vat'] ?? 0);
        $base = self::parseNumber($payment['cuocgoc']['total_tongcuoc'] ?? 0);
        $customerCommission = self::parseNumber($payment['cuocban']['total_hh_khachhang'] ?? 0);
        $saleBonus = self::parseNumber($payment['cuocvon']['bonus_sale_amount'] ?? 0);
        $estimatedProfit = $saleNoVat - $costNoVat - $customerCommission;
        $profit = $estimatedProfit - $saleBonus;
        $estimatedProfitRate = $saleNoVat > 0 ? round(($estimatedProfit * 100) / $saleNoVat, 2) : 0;
        $profitRate = $saleNoVat > 0 ? round(($profit * 100) / $saleNoVat, 2) : 0;

        return [
            'cuocban_no_vat' => $saleNoVat,
            'cuocban' => $sale,
            'cuocvon_no_vat' => $costNoVat,
            'cuocvon' => $cost,
            'cuocgoc_no_vat' => $baseNoVat,
            'cuocgoc' => $base,
            'loinhuantamtinh' => $estimatedProfit,
            'tysuattamtinh' => $estimatedProfitRate,
            'loinhuan' => $profit,
            'tysuat' => $profitRate,
            'tysuatloinhuan' => $profitRate,
        ];
    }
}
