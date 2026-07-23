<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAudience extends Model
{
    protected $fillable = ['project_id', 'name', 'pains', 'gains', 'behaviors'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
