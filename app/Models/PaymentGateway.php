<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * بوابة دفع تُدار من لوحة الآدمن. المفاتيح مشفّرة في العمود عبر cast،
 * فلا تظهر في قاعدة البيانات نصًّا ولا تحتاج وضعها في .env.
 */
class PaymentGateway extends Model
{
    protected $fillable = ['provider', 'label', 'mode', 'is_active', 'credentials', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * هل البوابة جاهزة فعلًا: مفعّلة ومفاتيحها موجودة.
     * البوابة اليدوية لا تحتاج مفاتيح فتُعدّ مهيأة متى فُعّلت.
     */
    public function isConfigured(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->provider === 'manual'
            || ! empty(array_filter($this->credentials ?? []));
    }
}
