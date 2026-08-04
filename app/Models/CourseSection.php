<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseSection extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'position',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'course_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            Content::class,
            'course_section_items',
            'course_section_id',
            'content_id',
        )->withPivot('position')->orderByPivot('position');
    }
}
