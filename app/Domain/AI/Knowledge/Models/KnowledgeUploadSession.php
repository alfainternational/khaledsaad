<?php

namespace App\Domain\AI\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeUploadSession extends Model
{
    protected $fillable = [
        'public_id', 'account_id', 'workspace_id', 'project_id', 'uploaded_by_user_id',
        'disk', 'path', 'original_name', 'mime_type', 'extension', 'byte_size',
        'chunk_size', 'chunk_count', 'sha256', 'status', 'expires_at',
    ];

    protected $hidden = ['disk', 'path'];

    protected $casts = [
        'account_id' => 'integer',
        'workspace_id' => 'integer',
        'project_id' => 'integer',
        'uploaded_by_user_id' => 'integer',
        'byte_size' => 'integer',
        'chunk_size' => 'integer',
        'chunk_count' => 'integer',
        'expires_at' => 'datetime',
    ];
}
