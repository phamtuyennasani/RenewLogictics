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
        'avatar',
        'fullname',
        'phone',
        'email',
        'address',
        'status',
        'role',
        'numb',
        'code',
        'options',
        'id_sale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'options' => 'array',
        'status'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Role constants (từ hệ thống cũ)
    const ROLE_ADMIN = 3;
    const ROLE_MANAGER = 2;
    const ROLE_USER = 1;

    // Cascade soft delete: xóa role trong pivot table trước khi soft delete user
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $user->roles()->detach();
        });
    }

    // Auth override
    public function getAuthPassword()
    {
        return $this->password;
    }

    protected function active(): Attribute
    {
        return Attribute::get(fn () => (bool) $this->status);
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
