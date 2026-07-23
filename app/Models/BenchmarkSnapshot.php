<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenchmarkSnapshot extends Model
{
    protected $fillable = [
        'metric', 'industry', 'geography', 'business_model',
        'value_low', 'value_high', 'unit', 'source_name', 'source_url', 'payload', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
            'value_low' => 'decimal:2',
            'value_high' => 'decimal:2',
        ];
    }

    public function isFresh(int $days): bool
    {
        return $this->fetched_at !== null && $this->fetched_at->gt(now()->subDays($days));
    }
}
