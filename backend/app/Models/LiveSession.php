<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LiveSession extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_DENIED = 'denied';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'device_id',
        'requested_by',
        'purpose',
        'request_camera',
        'request_microphone',
        'status',
        'signaling_token_hash',
        'device_offer_sdp',
        'admin_answer_sdp',
        'device_ice',
        'admin_ice',
        'expires_at',
        'responded_at',
        'started_at',
        'ended_at',
        'ended_by',
    ];

    protected function casts(): array
    {
        return [
            'request_camera' => 'boolean',
            'request_microphone' => 'boolean',
            'device_ice' => 'array',
            'admin_ice' => 'array',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LiveSession $session): void {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
