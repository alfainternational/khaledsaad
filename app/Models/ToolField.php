<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolField extends Model
{
    protected $fillable = [
        'tool_version_id', 'key', 'label', 'help', 'why', 'example', 'type', 'options', 'validation',
        'required', 'step', 'step_title', 'sort_order', 'visible_when', 'profile_key',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'visible_when' => 'array',
            'required' => 'boolean',
        ];
    }

    public function toolVersion(): BelongsTo
    {
        return $this->belongsTo(ToolVersion::class);
    }

    /**
     * قواعد التحقق تُبنى من قاعدة البيانات لا من كود ثابت، فتتغير مع الأداة.
     */
    public function validationRules(): array
    {
        $rules = $this->required ? ['required'] : ['nullable'];

        $rules[] = match ($this->type) {
            'number' => 'numeric',
            'multiselect' => 'array',
            'url' => 'url',
            default => 'string',
        };

        if (in_array($this->type, ['select', 'radio'], true) && $this->options) {
            $rules[] = 'in:'.implode(',', array_column($this->options, 'value'));
        }

        if ($this->validation) {
            $rules = array_merge($rules, explode('|', $this->validation));
        }

        return $rules;
    }

    /**
     * الحقل مرئي فقط عندما تتحقق شروطه على الإجابات الحالية.
     *
     * @param  array<string, mixed>  $answers
     */
    public function isVisible(array $answers): bool
    {
        if (! $this->visible_when) {
            return true;
        }

        foreach ($this->visible_when as $key => $expected) {
            $actual = $answers[$key] ?? null;
            $allowed = is_array($expected) ? $expected : [$expected];

            if (! in_array($actual, $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
