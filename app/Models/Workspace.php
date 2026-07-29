<?php

namespace App\Models;

use App\Services\Billing\SubscriptionManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends Model
{
    use HasFactory;

    protected $fillable = ['owner_id', 'guest_session_id', 'name', 'slug', 'type', 'monthly_query_limit'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function guestSession(): BelongsTo
    {
        return $this->belongsTo(GuestSession::class);
    }

    /**
     * كل مساحة جديدة تُزوَّد بمحفظة واشتراك مجاني فور إنشائها، فلا يوجد
     * مستخدم بلا رصيد أو خطة مهما كان مسار التسجيل (ويب، API، ترقية ضيف).
     */
    protected static function booted(): void
    {
        static::created(function (self $workspace): void {
            // مساحات الضيف لا تُزوَّد: الرصيد يخص الحسابات المسجلة.
            if ($workspace->owner_id !== null) {
                app(SubscriptionManager::class)->ensure($workspace);
            }
        });
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CreditWallet::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function isGuest(): bool
    {
        return $this->owner_id === null && $this->guest_session_id !== null;
    }
}
