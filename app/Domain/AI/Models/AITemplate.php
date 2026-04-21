<?php

namespace App\Domain\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AITemplate extends Model
{
    protected $table = 'ai_templates';

    protected $fillable = [
        'code',
        'name',
        'description',
        'prompt_template',
        'model',
        'credit_cost',
        'status',
        'module',
        'domain',
        'system_role',
        'output_contract_json',
    ];

    protected $casts = [
        'output_contract_json' => 'array',
    ];

    public function generations(): HasMany
    {
        return $this->hasMany(AIGeneration::class, 'template_id');
    }
}
