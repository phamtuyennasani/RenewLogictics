<?php

namespace App\Support;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\User;

class OrderAccess
{
    public static function canView(User $user, Order $order): bool
    {
        if ($user->hasAnyRole(['admin', 'manager', 'ketoan'])) {
            return true;
        }

        if ($user->hasRole('sale')) {
            return (int) $order->id_sale === (int) $user->id;
        }

        if ($user->hasRole('ctv')) {
            return (int) $order->id_customer === (int) $user->id;
        }

        if ($user->hasRole('cs')) {
            return blank($order->id_cs) || (int) $order->id_cs === (int) $user->id;
        }

        return false;
    }

    public static function canEditOrder(User $user, Order $order): bool
    {
        if ($order->lock_order) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        if ($user->hasRole('sale')) {
            return self::canView($user, $order)
                && in_array($order->bill_status, [OrderStatusEnum::MOI_TAO, OrderStatusEnum::DA_XAC_NHAN], true);
        }

        if ($user->hasRole('cs')) {
            return self::canView($user, $order);
        }

        return false;
    }

    public static function canEditPayment(User $user, Order $order): bool
    {
        if ($order->lock_order) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'manager', 'ketoan'])
            || self::canEditOrder($user, $order);
    }

    public static function canToggleLock(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public static function assignCsOnEdit(User $user, Order $order): void
    {
        if (! $user->hasRole('cs') || filled($order->id_cs)) {
            return;
        }

        $order->forceFill(['id_cs' => $user->id])->save();
        $order->refresh();
    }
}
