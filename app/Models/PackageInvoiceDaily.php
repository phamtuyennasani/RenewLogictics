<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageInvoiceDaily extends Model
{
    use HasFactory;

    protected $table = 'package_invoices_daily';

    protected $fillable = [
        'id_order',
        'package_code',
        'id_ketoan',
        'so_hd',
        'ngay_hd',
        'total',
    ];

    protected $casts = [
        'ngay_hd' => 'datetime',
        'total' => 'decimal:0',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }
}