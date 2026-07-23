<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PromptVersion extends Model
{
    protected $fillable = ['tool_version_id', 'stage', 'tier', 'content', 'status', 'locked_at'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // BR-012: بعد أول استخدام يصبح البرومبت غير قابل للتعديل. تعديله يعني
        // أن تقريرين بنفس الإصدار أُنتجا بتعليمات مختلفة — وهذا يبطل المقارنة.
        static::updating(function (self $prompt): void {
            if ($prompt->getOriginal('locked_at') !== null && $prompt->isDirty('content')) {
                throw new RuntimeException('لا يمكن تعديل برومبت مستخدم. أنشئ إصدار أداة جديدًا.');
            }
        });
    }

    public function lock(): void
    {
        if ($this->locked_at === null) {
            $this->forceFill(['locked_at' => now()])->save();
        }
    }

    public function toolVersion(): BelongsTo
    {
        return $this->belongsTo(ToolVersion::class);
    }
}
