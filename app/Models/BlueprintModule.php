<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueprintModule extends Model
{
    protected $fillable = ['blueprint_version_id', 'diagnostic_module_id', 'importance', 'required', 'activation_rules', 'stop_rules', 'sort_order'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'activation_rules' => 'array', 'stop_rules' => 'array'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(DiagnosticModule::class, 'diagnostic_module_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ModuleQuestion::class)->orderBy('sort_order');
    }
}
