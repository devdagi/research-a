<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceStatus extends Model
{
    protected $fillable = [
        'device_id',
        'battery_level',
        'is_charging',
        'network_type',
        'is_online',
        'extras',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'is_charging' => 'boolean',
            'is_online' => 'boolean',
            'extras' => 'array',
            'reported_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
