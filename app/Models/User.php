<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, SoftDeletes, Notifiable;

    protected $table = 'user';

    protected $fillable = [
        'username',
        'password',
        'confirm_code',
        'avatar',
        'fullname',
        'phone',
        'email',
        'address',
        'login_session',
        'user_token',
        'lastlogin',
        'status',
        'role',
        'secret_key',
        'numb',
        'code',
        'id_province',
        'id_ward',
        'options',
        'id_sale',
        'id_permission',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'options' => 'array',
        'lastlogin' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Role constants (từ hệ thống cũ)
    const ROLE_ADMIN = 3;
    const ROLE_MANAGER = 2;
    const ROLE_USER = 1;

    // Auth override
    public function getAuthPassword()
    {
        return $this->password;
    }

    // Route key for UUID
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // Active status accessor (từ hệ thống cũ)
    protected function active(): Attribute
    {
        return Attribute::get(function () {
            if (empty($this->status)) {
                return false;
            }
            return str_contains($this->status, 'hienthi');
        });
    }

    // Relationships
    public function city()
    {
        return $this->belongsTo(City::class, 'id_province');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'id_ward');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'id_sale');
    }

    public function orderActions()
    {
        return $this->hasOne(OrderAction::class, 'id_user')->latestOfMany('updated_at');
    }

    public function pickups()
    {
        return $this->hasMany(Pickup::class, 'id_user');
    }

    public function members()
    {
        return $this->hasMany(Member::class, 'id_sale');
    }

    // Check role helpers
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
