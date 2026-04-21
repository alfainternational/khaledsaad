<?php

namespace App\Domain\Entitlement\Models;

use Illuminate\Database\Eloquent\Model;

class Entitlement extends Model
{
    protected $fillable = [
        'scope_type',
        'scope_id',
        'key',
        'value_type',
        'value',
        'source',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function decodedValue(): mixed
    {
        $value = $this->value;

        if (! is_array($value) || ! array_key_exists('value', $value)) {
            return $value;
        }

        return $value['value'];
    }
}
