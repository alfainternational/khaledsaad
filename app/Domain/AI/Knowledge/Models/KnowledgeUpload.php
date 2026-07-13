<?php

namespace App\Domain\AI\Knowledge\Models;

use App\Domain\Account\Models\Account;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeUpload extends Model
{
    protected $fillable = [
        'public_id',
        'account_id',
        'workspace_id',
        'project_id',
        'uploaded_by_user_id',
        'knowledge_source_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'byte_size',
        'sha256',
        'status',
        'error_code',
        'extraction_meta_json',
    ];

    protected $hidden = ['disk', 'path'];

    protected $casts = [
        'account_id' => 'integer',
        'workspace_id' => 'integer',
        'project_id' => 'integer',
        'uploaded_by_user_id' => 'integer',
        'knowledge_source_id' => 'integer',
        'byte_size' => 'integer',
        'extraction_meta_json' => 'array',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }
}
