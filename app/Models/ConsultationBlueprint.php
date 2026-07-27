<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultationBlueprint extends Model
{
    protected $fillable = ['key', 'name', 'status', 'current_version_id'];

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ConsultationBlueprintVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ConsultationBlueprintVersion::class);
    }
}
