<?php

namespace App\Domain\Execution\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionAsset extends Model
{
    use HasPublicId;

    public const TYPES = ['copy', 'design_brief', 'dev_brief', 'ad', 'measurement', 'other'];

    protected $fillable = [
        'public_id',
        'execution_package_id',
        'type',
        'title',
        'body',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function executionPackage(): BelongsTo
    {
        return $this->belongsTo(ExecutionPackage::class);
    }
}
