<?php

namespace App\Domain\Billing\Models;

use App\Domain\Entitlement\Models\Entitlement;
use App\Enums\PlanStatus;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasPublicId;

    protected $fillable = [
        'public_id',
        'code',
        'name_ar',
        'name_en',
        'monthly_price',
        'annual_price',
        'status',
        'features_json',
        'paypal_plan_id_monthly',
        'paypal_plan_id_annual',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'features_json' => 'array',
        'status' => PlanStatus::class,
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class, 'scope_id')->where('scope_type', 'plan');
    }
}
