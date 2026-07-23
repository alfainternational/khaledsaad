<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolRunAnswer extends Model
{
    public const SOURCE_USER = 'user';

    public const SOURCE_EXTRACTED = 'extracted';

    public const SOURCE_PROFILE = 'profile';

    protected $fillable = ['tool_run_id', 'field_key', 'value_json', 'source'];

    protected function casts(): array
    {
        return ['value_json' => 'array'];
    }

    public function toolRun(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class);
    }
}
