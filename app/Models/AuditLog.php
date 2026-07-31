<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل الإجراءات الحساسة (بند ٢٢): تعديل برومبت، سكّ إصدار، استيراد يدوي،
 * انتحال مستخدم. سطر واحد لكل حدث، والكتابة لا تُفشل العملية الأصلية أبدًا.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'meta', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function write(string $action, ?Model $subject = null, array $meta = []): void
    {
        try {
            static::create([
                'actor_id' => auth()->id(),
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'meta' => $meta === [] ? null : $meta,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // السجل مرآة لا حارس — فشله لا يوقف الإجراء نفسه.
        }
    }
}
