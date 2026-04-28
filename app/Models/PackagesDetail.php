<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagesDetail extends Model
{
    use HasFactory;

    protected $table = 'packages_detail';

    protected $fillable = [
        'id_package',
        'id_order',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class, 'id_package');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}