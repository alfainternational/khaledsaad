<?php

namespace App\Domain\Intelligence\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pre-registration diagnosis case (Phase أ funnel).
 * A temporary, unguessable (ULID) record that holds a guest's quick diagnosis before
 * they create an account. Converts into a Workspace + Project on registration.
 */
class DiagnosisCase extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'input_url',
        'business_name',
        'case_type',
        'goal',
        'competitors_json',
        'sector',
        'email',
        'email_captured_at',
        'status',
        'executive_score',
        'integrity_status',
        'report_json',
        'failure_reason',
        'expires_at',
        'converted_workspace_id',
        'converted_project_id',
        'ip',
    ];

    protected $casts = [
        'competitors_json' => 'array',
        'report_json' => 'array',
        'email_captured_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isAnalyzing(): bool
    {
        return in_array($this->status, ['queued', 'analyzing'], true);
    }

    public function hasEmail(): bool
    {
        return filled($this->email);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
