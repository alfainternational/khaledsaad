<?php

namespace App\Models;

use App\Modules\Shared\I18n\StoredText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * عنصر ميزة حقيقي في الفهرس.
 *
 * المفتاح (key) ليس نصًّا تسويقيًا: هو ما يسأل عنه الكود عند البوابة
 * (Entitlements::allows / limit). ولذلك لا يُحرَّر مفتاح ميزة من نوع gate
 * بعد إنشائها — تحريره يقطع الصلة بنقطة التطبيق.
 */
class Feature extends Model
{
    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_LIMIT = 'limit';

    public const TYPE_QUOTA = 'quota';

    public const ENFORCEMENT_GATE = 'gate';

    public const ENFORCEMENT_DISPLAY = 'display';

    protected $fillable = [
        'key', 'name', 'description', 'group', 'type', 'unit',
        'enforcement', 'default_enabled', 'default_value', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_features')
            ->withPivot(['enabled', 'value', 'note', 'sort_order'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, [self::TYPE_LIMIT, self::TYPE_QUOTA], true);
    }

    public function isEnforced(): bool
    {
        return $this->enforcement === self::ENFORCEMENT_GATE;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_LIMIT => __('حد أقصى'),
            self::TYPE_QUOTA => __('حصة شهرية'),
            default => __('تشغيل/إيقاف'),
        };
    }

    public function groupLabel(): string
    {
        return match ($this->group) {
            'core' => __('الأساس'),
            'reports' => __('التقارير'),
            'growth' => __('محرك النمو'),
            'support' => __('الدعم والخدمة'),
            default => __('عام'),
        };
    }

    /** الاسم كما يُعرض: مترجَمًا إن خُبزت له ترجمة. */
    public function displayName(): string
    {
        return StoredText::of($this->name);
    }

    /**
     * صياغة القيمة كما تُعرض للعميل: «10 مشاريع» أو «بلا حد» أو اسم الميزة.
     */
    public function describeValue(?int $value): string
    {
        $name = $this->displayName();

        if (! $this->isNumeric()) {
            return $name;
        }

        if ($value === null) {
            return $name.' — '.__('بلا حد');
        }

        // الوحدة جزء من الجملة لا زينة: تركها عربية يُنتج «10 مشروع» داخل
        // سطر إنجليزي، وهو أوضح في الخطأ من غياب الترجمة أصلًا.
        return $name.' — '.$value.($this->unit ? ' '.StoredText::of($this->unit) : '');
    }
}
