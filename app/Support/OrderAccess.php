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

        // OPS: chỉ xem đơn chưa có OPS phụ trách hoặc đơn của chính mình.
        if ($user->hasRole('ops')) {
            return blank($order->id_ops) || (int) $order->id_ops === (int) $user->id;
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

        if ($user->hasAnyRole(['admin', 'manager', 'ketoan'])) {
            // Admin/Manager/Ketoan: chỉ cập nhật giá khi đơn đã xác nhận trở đi
            return $order->bill_status !== OrderStatusEnum::MOI_TAO;
        }

        if ($user->hasRole('sale')) {
            // Sale: chỉ khi đơn mới tạo hoặc đã xác nhận
            if (! in_array($order->bill_status, [OrderStatusEnum::MOI_TAO, OrderStatusEnum::DA_XAC_NHAN], true)) {
                return false;
            }

            // Sale: không cập nhật nếu đã chốt cước bán
            if (filled($order->sale_price_locked_at)) {
                return false;
            }

            return self::canView($user, $order);
        }

        return false;
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

    /**
     * Khi OPS thao tác nghiệp vụ (cập nhật cân nặng, invoice hàng hóa, tạo pickup...)
     * trên đơn CHƯA có OPS phụ trách → tự gán chính họ vào id_ops.
     */
    public static function assignOpsOnAction(User $user, Order $order): void
    {
        if (! $user->hasRole('ops') || filled($order->id_ops)) {
            return;
        }

        $order->forceFill(['id_ops' => $user->id])->save();
        $order->refresh();
    }

    /**
     * Đổi CS phụ trách: chỉ admin/manager, đổi tự do (kể cả đã có người), trừ khi đơn bị khóa.
     */
    public static function canAssignCs(User $user, Order $order): bool
    {
        if ($order->lock_order) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'manager']);
    }

    /**
     * Đổi OPS phụ trách:
     * - admin/manager: đổi tự do, kể cả khi đơn đã có OPS.
     * - sale/cs: chỉ được chọn khi đơn CHƯA có OPS phụ trách.
     * Đơn bị khóa thì không ai đổi được.
     */
    public static function canAssignOps(User $user, Order $order): bool
    {
        if ($order->lock_order) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        if ($user->hasAnyRole(['sale', 'cs'])) {
            return blank($order->id_ops) && self::canView($user, $order);
        }

        return false;
    }
}
