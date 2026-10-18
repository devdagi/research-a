<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeviceEnrollmentCode extends Model
{
    protected $fillable = [
        'code_hash',
        'code_hint',
        'label',
        'created_by',
        'expires_at',
        'used_at',
        'used_by_device_id',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function generatePlaintext(): string
    {
        return strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
    }

    public static function hashCode(string $plaintext): string
    {
        return hash('sha256', strtoupper(trim($plaintext)));
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed();
    }

    public function matches(string $plaintext): bool
    {
        return hash_equals($this->code_hash, self::hashCode($plaintext));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedByDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'used_by_device_id');
    }
}
