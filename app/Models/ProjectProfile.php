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
        'monthly_budget', 'primary_goal', 'value_proposition', 'channels', 'extras',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'extras' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
