<?php

namespace App\Domain\Execution\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionTask extends Model
{
    use HasPublicId;

    public const STATUSES = ['pending', 'in_progress', 'done'];

    protected $fillable = [
        'public_id',
        'execution_package_id',
        'title',
        'description',
        'assigned_to',
        'status',
        'due_date',
        'order_index',
    ];

    protected $casts = [
        'due_date' => 'date',
        'order_index' => 'integer',
    ];

    public function executionPackage(): BelongsTo
    {
        return $this->belongsTo(ExecutionPackage::class);
    }
}
