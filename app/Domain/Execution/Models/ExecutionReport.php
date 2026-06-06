<?php

namespace App\Domain\Execution\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionReport extends Model
{
    use HasPublicId;

    public const PHASES = ['discovery', 'planning', 'execution', 'validation'];

    protected $fillable = [
        'public_id',
        'execution_package_id',
        'phase',
        'progress',
        'notes_json',
        'metrics_json',
    ];

    protected $casts = [
        'progress' => 'integer',
        'notes_json' => 'array',
        'metrics_json' => 'array',
    ];

    public function executionPackage(): BelongsTo
    {
        return $this->belongsTo(ExecutionPackage::class);
    }
}
