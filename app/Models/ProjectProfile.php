<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'business_model', 'description', 'geography', 'website',
        'monthly_budget', 'budget_includes_agency_fee', 'agency_services', 'brief',
        'primary_goal', 'value_proposition', 'channels', 'extras',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'extras' => 'array',
            'agency_services' => 'array',
            'brief' => 'array',
            // null مقصود: «لم يُحدَّد» حالة ثالثة لا تُطوى في false.
            'budget_includes_agency_fee' => 'boolean',
        ];
    }

    /**
     * بند واحد من موجز التكليف بأمان.
     */
    public function brief(string $key, mixed $default = null): mixed
    {
        return ($this->brief ?? [])[$key] ?? $default;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
