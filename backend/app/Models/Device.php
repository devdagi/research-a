<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Device extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_UNENROLLED = 'unenrolled';

    protected $fillable = [
        'uuid',
        'name',
        'platform',
        'model',
        'os_version',
        'app_version',
        'fcm_token',
        'status',
        'enrolled_at',
        'last_seen_at',
        'revoked_at',
        'enrolled_by',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Device $device): void {
            if (empty($device->uuid)) {
                $device->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOnline(?int $thresholdMinutes = null): bool
    {
        if (! $this->last_seen_at) {
            return false;
        }

        $minutes = $thresholdMinutes ?? (int) config('cdm.device_online_threshold_minutes', 5);

        return $this->last_seen_at->gt(now()->subMinutes($minutes));
    }

    public function touchLastSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(DeviceStatus::class);
    }

    public function latestStatus(): HasOne
    {
        return $this->hasOne(DeviceStatus::class)->latestOfMany('reported_at');
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(DeviceLocation::class)->latestOfMany('recorded_at');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(DeviceLocation::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(DeviceConsent::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function hasActiveConsent(string $type): bool
    {
        $latest = $this->consents()
            ->where('type', $type)
            ->orderByDesc('decided_at')
            ->first();

        return $latest && $latest->status === DeviceConsent::STATUS_GRANTED;
    }

    /**
     * Latest decision per permission type for admin lists.
     *
     * @return array{accepted: list<array{type: string, label: string, status: string, decided_at: ?\Illuminate\Support\Carbon}>, not_accepted: list<array{type: string, label: string, status: string, decided_at: ?\Illuminate\Support\Carbon}>}
     */
    public function permissionLists(): array
    {
        $history = $this->relationLoaded('consents')
            ? $this->consents
            : $this->consents()->orderByDesc('decided_at')->orderByDesc('id')->get();

        $latestByType = [];
        foreach ($history->sortByDesc(fn ($c) => sprintf('%s-%020d', $c->decided_at?->format('YmdHis') ?? '0', $c->id)) as $consent) {
            if (! isset($latestByType[$consent->type])) {
                $latestByType[$consent->type] = $consent;
            }
        }

        $accepted = [];
        $notAccepted = [];

        foreach (DeviceConsent::catalog() as $type => $label) {
            $latest = $latestByType[$type] ?? null;
            $row = [
                'type' => $type,
                'label' => $label,
                'status' => $latest?->status ?? 'not_decided',
                'decided_at' => $latest?->decided_at,
            ];

            if ($latest && $latest->status === DeviceConsent::STATUS_GRANTED) {
                $accepted[] = $row;
            } else {
                $notAccepted[] = $row;
            }
        }

        return ['accepted' => $accepted, 'not_accepted' => $notAccepted];
    }
}
