<?php

namespace App\Domain\AI\Knowledge\Models;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeSource extends Model
{
    protected $fillable = [
        'account_id',
        'workspace_id',
        'project_id',
        'scope_key',
        'kind',
        'canonical_uri',
        'identity_hash',
        'trust_score',
        'visibility',
        'meta_json',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'workspace_id' => 'integer',
        'project_id' => 'integer',
        'trust_score' => 'integer',
        'meta_json' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function scopeInScope(Builder $query, KnowledgeScope $scope): Builder
    {
        $query
            ->where('scope_key', $scope->key())
            ->where('visibility', $scope->visibility);

        foreach ([
            'account_id' => $scope->accountId,
            'workspace_id' => $scope->workspaceId,
            'project_id' => $scope->projectId,
        ] as $column => $id) {
            $id === null ? $query->whereNull($column) : $query->where($column, $id);
        }

        return $query;
    }
}
