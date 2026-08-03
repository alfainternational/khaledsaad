<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentResource extends Model
{
    public const TYPE_FILE = 'file';

    public const TYPE_LINK = 'link';

    protected $fillable = [
        'content_id',
        'type',
        'title',
        'content_media_id',
        'url',
        'position',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(ContentMedia::class, 'content_media_id');
    }
}
