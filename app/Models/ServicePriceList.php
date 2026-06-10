<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePriceList extends Model
{
    protected $fillable = [
        'name',
        'service_id',
        'created_by',
        'updated_by',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(News::class, 'service_id');
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'service_price_list_countries', 'service_price_list_id', 'country_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ServicePriceDetail::class)->orderBy('weight_from')->orderBy('weight_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
