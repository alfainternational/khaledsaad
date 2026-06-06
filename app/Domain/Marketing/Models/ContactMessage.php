<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_NEEDS_FOLLOWUP = 'needs_followup';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_GENERAL = 'general';

    public const TYPE_CONSULTATION = 'consultation';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'body',
        'message_type',
        'source',
        'payload',
        'status',
        'read_at',
        'converted_workspace_id',
        'converted_client_id',
        'converted_project_id',
        'converted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'جديد',
            self::STATUS_READ => 'مقروء',
            self::STATUS_QUALIFIED => 'مؤهل',
            self::STATUS_NEEDS_FOLLOWUP => 'يحتاج متابعة',
            self::STATUS_CONVERTED => 'تم تحويله',
            self::STATUS_ARCHIVED => 'مؤرشف',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_GENERAL => 'رسالة عامة',
            self::TYPE_CONSULTATION => 'استشارة مشروع',
        ];
    }

    public function convertedWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'converted_workspace_id');
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function convertedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'converted_project_id');
    }

    public function isConsultation(): bool
    {
        return $this->message_type === self::TYPE_CONSULTATION;
    }

    /**
     * @return array<string, mixed>
     */
    public function toProjectBriefPayload(): array
    {
        $payload = is_array($this->payload) ? $this->payload : [];

        return [
            'business' => [
                'summary' => (string) data_get($payload, 'business.summary', ''),
                'offer' => (string) data_get($payload, 'business.offer', ''),
                'market' => (string) data_get($payload, 'business.market', ''),
            ],
            'audience' => [
                'ideal_customer' => (string) data_get($payload, 'audience.ideal_customer', ''),
                'pain_points' => (string) data_get($payload, 'audience.pain_points', ''),
                'buying_trigger' => (string) data_get($payload, 'audience.buying_trigger', ''),
            ],
            'goals' => [
                'primary_goal' => (string) data_get($payload, 'goals.primary_goal', ''),
                'success_metric' => (string) data_get($payload, 'goals.success_metric', ''),
                'timeframe' => (string) data_get($payload, 'goals.timeframe', ''),
            ],
            'current_marketing' => [
                'channels' => implode('، ', array_filter((array) data_get($payload, 'current_marketing.channels', []))),
                'current_state' => (string) data_get($payload, 'current_marketing.current_state', ''),
                'assets' => (string) data_get($payload, 'current_marketing.assets', ''),
            ],
            'brand' => [
                'voice' => (string) data_get($payload, 'brand.voice', ''),
                'tone_rules' => (string) data_get($payload, 'brand.tone_rules', ''),
            ],
            'positioning' => [
                'edge' => (string) data_get($payload, 'positioning.edge', ''),
                'promise' => (string) data_get($payload, 'positioning.promise', ''),
            ],
            'competition' => [
                'competitors' => implode('، ', array_filter((array) data_get($payload, 'competition.competitors', []))),
                'gap' => (string) data_get($payload, 'competition.gap', ''),
            ],
            'execution' => [
                'priority' => (string) data_get($payload, 'execution.priority', ''),
                'next_asset' => implode('، ', array_filter((array) data_get($payload, 'services', []))),
                'delivery_notes' => (string) data_get($payload, 'notes.additional_context', ''),
            ],
            'commercial' => [
                'budget_range' => (string) data_get($payload, 'commercial.budget_range', ''),
                'decision_maker' => (string) data_get($payload, 'contact.company_name', ''),
            ],
        ];
    }
}
