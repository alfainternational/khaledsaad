<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * إعداد مفرد قابل للتحرير من لوحة الآدمن.
 * القيم تُقرأ عبر الكاش حتى لا يضرب كل طلب قاعدة البيانات.
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::allCached();

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        return $all[$key];
    }

    public static function put(string $key, mixed $value, string $group = 'general', string $type = 'string'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => static::encode($value, $type), 'group' => $group, 'type' => $type],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function allCached(): array
    {
        return Cache::rememberForever('settings.all', fn () => static::all()
            ->mapWithKeys(fn (self $setting) => [$setting->key => static::decode($setting->value, $setting->type)])
            ->all());
    }

    private static function encode(mixed $value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            // الأسرار (مفاتيح API) تُخزَّن مشفّرة، لا نصًّا صريحًا في قاعدة البيانات.
            'secret' => Crypt::encryptString((string) $value),
            default => (string) $value,
        };
    }

    private static function decode(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'json' => json_decode($value, true),
            'bool' => $value === '1',
            'int' => (int) $value,
            'secret' => self::tryDecrypt($value),
            default => $value,
        };
    }

    private static function tryDecrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            // قيمة أُدخلت قبل تفعيل التشفير تُعاد كما هي بدل أن تنكسر.
            return $value;
        }
    }
}
