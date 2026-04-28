<?php

namespace App\Actions\Order;

class CalculateChargeableWeightAction
{
    /**
     * Calculate volumetric weight and chargeable weight
     *
     * @param float $length Length in cm
     * @param float $width Width in cm
     * @param float $height Height in cm
     * @param float $grossWeight Gross weight in kg
     * @param float $dim DIM factor (default 6000)
     * @return array ['v_weight' => float, 'c_weight' => float]
     */
    public function execute(
        float $length,
        float $width,
        float $height,
        float $grossWeight,
        float $dim = 6000
    ): array {
        // Calculate volumetric weight
        $vWeight = ($length * $width * $height) / $dim;

        // Chargeable weight is the greater of volumetric or gross weight
        $cWeight = max($vWeight, $grossWeight);

        // Apply rounding rules
        if ($cWeight < 21) {
            // Under 21kg: round to nearest 0.5kg
            $cWeight = ceil($cWeight / 0.5) * 0.5;
        } else {
            // 21kg and above: round up to nearest whole number
            $cWeight = ceil($cWeight);
        }

        return [
            'v_weight' => round($vWeight, 3),
            'c_weight' => round($cWeight, 3),
        ];
    }
}
