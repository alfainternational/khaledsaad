<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * لوحة الجمهور الاصطناعي: شخصيات ثابتة لكل مشروع تُختبر عليها الرسائل
 * قبل الإنفاق — بديل مجموعات التركيز لمن لا يملك ميزانيتها.
 */
class PersonaPanel extends Model
{
    protected $fillable = ['project_id', 'personas', 'source', 'generated_at'];

    protected function casts(): array
    {
        return [
            'personas' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(PersonaTest::class)->latest();
    }
}
