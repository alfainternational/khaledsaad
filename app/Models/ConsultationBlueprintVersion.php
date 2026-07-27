<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultationBlueprintVersion extends Model
{
    protected $fillable = ['consultation_blueprint_id', 'version', 'status', 'settings', 'published_at', 'locked_at'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'published_at' => 'datetime', 'locked_at' => 'datetime'];
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(ConsultationBlueprint::class, 'consultation_blueprint_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(BlueprintModule::class, 'blueprint_version_id')->orderBy('sort_order');
    }
}
