<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * عميل متوقع: شخص حقيقي بأسمه، يعرفه صاحب المشروع ولم يشترِ بعد.
 *
 * الفرق عن الشخصية ليس في الحجم بل في النوع: الشخصية نموذج نستنتجه ونختبر
 * عليه، وهذا إنسان لا يُحاكى رأيه. لذلك له رسالة ولا درجة له.
 */
class Prospect extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_WON = 'won';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * حرارة النية كما يقدّرها صاحب المشروع — تقدير صريح لا قياس.
     */
    public const TEMPERATURES = [
        'cold' => 'بارد — لم يبدِ اهتمامًا بعد',
        'warm' => 'دافئ — أبدى اهتمامًا',
        'hot' => 'ساخن — يقارن الآن',
    ];

    protected $fillable = [
        'project_id', 'user_id', 'name', 'organization', 'role', 'city',
        'notes', 'interests', 'temperature', 'preferred_channel', 'persona_key', 'status',
    ];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProspectMessage::class);
    }

    public function latestMessage(): ?ProspectMessage
    {
        return $this->messages()
            ->where('status', '!=', ProspectMessage::STATUS_ARCHIVED)
            ->latest('id')->first();
    }

    public function temperatureLabel(): string
    {
        return self::TEMPERATURES[$this->temperature] ?? self::TEMPERATURES['warm'];
    }

    /**
     * ما يُرسل إلى النموذج: ما يخدم الصياغة فقط.
     *
     * لا معرّف قاعدة بيانات ولا حقول إدارية — ما لا يغيّر النص لا يُرسل،
     * وهذا يقلّل الرموز ويقلّل ما يغادر النظام من بيانات شخص حقيقي.
     *
     * @return array<string, mixed>
     */
    public function briefing(string $key): array
    {
        return array_filter([
            'prospect_key' => $key,
            'name' => $this->name,
            'organization' => $this->organization,
            'role' => $this->role,
            'city' => $this->city,
            'interests' => $this->interests ?: null,
            'temperature' => $this->temperatureLabel(),
            'what_we_know' => $this->notes,
        ], fn ($value) => filled($value));
    }
}
