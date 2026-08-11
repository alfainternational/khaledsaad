<?php

namespace App\Modules\Insights\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * زيارة واحدة متصلة: من أول صفحة حتى ثلاثين دقيقة من السكون.
 */
class VisitorSession extends Model
{
    protected $fillable = [
        'uuid', 'visitor_id', 'user_id', 'started_at', 'last_activity_at', 'ended_at',
        'active_seconds', 'page_views_count', 'events_count', 'entry_path', 'exit_path',
        'channel', 'platform', 'source', 'medium', 'campaign', 'term', 'content',
        'referrer_host', 'referrer_url', 'landing_query',
        'device_type', 'browser', 'browser_version', 'os', 'os_version', 'user_agent',
        'screen_width', 'screen_height', 'viewport_width', 'viewport_height',
        'country', 'location_basis', 'location_evidence', 'timezone', 'language',
        'is_returning', 'is_bounce', 'is_bot', 'bot_name', 'bot_owner', 'is_staff',
        'conversion_name', 'converted_at', 'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
            'converted_at' => 'datetime',
            'active_seconds' => 'integer',
            'page_views_count' => 'integer',
            'events_count' => 'integer',
            'screen_width' => 'integer',
            'screen_height' => 'integer',
            'viewport_width' => 'integer',
            'viewport_height' => 'integer',
            'is_returning' => 'boolean',
            'is_bounce' => 'boolean',
            'is_bot' => 'boolean',
            'is_staff' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(VisitorPageView::class, 'session_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitorEvent::class, 'session_id');
    }

    /**
     * الجمهور البشري: ما تُبنى عليه كل نسبة تُعرض كسوق.
     *
     * البوتات وزيارات الإدارة تبقى في الجدول ولا تدخل هنا. خلطها يجعل
     * «ارتفاع الزيارات ٤٠٪» احتفالًا بزحف بوت أو بجلسة تطوير.
     */
    public function scopeHuman(Builder $query): Builder
    {
        $query->where('is_bot', false);

        if (! config('insights.count_staff')) {
            $query->where('is_staff', false);
        }

        return $query;
    }

    public function scopeBots(Builder $query): Builder
    {
        return $query->where('is_bot', true);
    }

    /** الجلسات الحيّة الآن: نشاط خلال آخر خمس دقائق. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('last_activity_at', '>=', now()->subMinutes(5));
    }

    /** هل انتهت نافذة الجلسة فوجب فتح جلسة جديدة للطلب التالي. */
    public function hasExpired(): bool
    {
        return $this->last_activity_at->lt(
            now()->subMinutes((int) config('insights.session_timeout_minutes', 30))
        );
    }

    /** مدة البقاء بصيغة عربية مقروءة: «٣ د ٢٠ ث». */
    public function durationForHumans(): string
    {
        return self::secondsForHumans($this->active_seconds);
    }

    public static function secondsForHumans(int $seconds): string
    {
        /*
         * الصفر ليس «أقل من ثانية» بل «لا قياس».
         *
         * الزمن يأتي من نبض المتصفح وحده: زائرٌ بلا جافاسكربت، أو غادر
         * قبل أول نبضة، لا زمن له — وعرضه «أقل من ثانية» يحوّل فجوة
         * قياس إلى رقمٍ يبدو مقيسًا، وهو ما يمنعه §٤.٣ نصًّا.
         */
        if ($seconds <= 0) {
            return '—';
        }

        /*
         * الاختصارات مفاتيح ترجمة لا حروف موصولة: «ث» و«د» و«س» لا معنى
         * لها في لغة أخرى، والوصل يثبّت ترتيبها كذلك — والإنجليزية تكتب
         * «5m 20s» لا «5 m 20 s».
         */
        if ($seconds < 60) {
            return __(':seconds ث', ['seconds' => $seconds]);
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        if ($minutes < 60) {
            return $rest > 0
                ? __(':minutes د :seconds ث', ['minutes' => $minutes, 'seconds' => $rest])
                : __(':minutes د', ['minutes' => $minutes]);
        }

        $hours = intdiv($minutes, 60);

        return __(':hours س :minutes د', ['hours' => $hours, 'minutes' => $minutes % 60]);
    }
}
