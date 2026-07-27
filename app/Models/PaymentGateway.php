<?php

namespace App\Models;

use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Database\Eloquent\Model;

/**
 * بوابة دفع تُدار من لوحة الآدمن. المفاتيح مشفّرة في العمود عبر cast،
 * فلا تظهر في قاعدة البيانات نصًّا ولا تحتاج وضعها في .env.
 */
class PaymentGateway extends Model
{
    protected $fillable = [
        'provider', 'label', 'mode', 'is_active', 'credentials',
        'currency', 'fx_rate', 'instructions', 'sort_order', 'is_default',
        'health_status', 'last_health_check_at', 'last_health_message',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'fx_rate' => 'float',
            'last_health_check_at' => 'datetime',
            // التشفير عبر APP_KEY. القيمة تُخزَّن مشفّرة وتُفكّ عند القراءة.
            'credentials' => 'encrypted:array',
        ];
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    /**
     * قيمة اعتماد واحدة بأمان.
     */
    public function credential(string $key, mixed $default = null): mixed
    {
        return ($this->credentials ?? [])[$key] ?? $default;
    }

    /**
     * هل البوابة جاهزة فعلًا: مفعّلة ولا ينقصها مفتاح إلزامي.
     * البوابة اليدوية لا تحتاج مفاتيح فتُعدّ مهيأة متى فُعّلت.
     */
    public function isConfigured(): bool
    {
        return $this->is_active && $this->hasRequiredCredentials();
    }

    public function isHealthy(): bool
    {
        return $this->provider === 'manual' || $this->health_status === 'healthy';
    }

    /**
     * المفاتيح الإلزامية لهذا المزوّد كلها موجودة.
     * (الفحص منفصل عن التفعيل حتى نتحقق قبل التفعيل لا بعده.)
     */
    public function hasRequiredCredentials(): bool
    {
        $required = PaymentGatewayManager::catalogue()[$this->provider]['required'] ?? [];

        foreach ($required as $key) {
            if (blank($this->credential($key))) {
                return false;
            }
        }

        return true;
    }
}
