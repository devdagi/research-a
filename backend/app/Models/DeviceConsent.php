<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceConsent extends Model
{
    public const TYPE_LOCATION = 'location';

    public const TYPE_LIVE_CAMERA = 'live_camera';

    public const TYPE_LIVE_MICROPHONE = 'live_microphone';

    public const TYPE_NOTIFICATIONS = 'notifications';

    public const STATUS_GRANTED = 'granted';

    public const STATUS_DENIED = 'denied';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @return array<string, string> type => label */
    public static function catalog(): array
    {
        return [
            self::TYPE_LOCATION => 'Location sharing',
            self::TYPE_LIVE_MICROPHONE => 'Live microphone',
            self::TYPE_LIVE_CAMERA => 'Live camera',
            self::TYPE_NOTIFICATIONS => 'Notifications',
        ];
    }

    protected $fillable = [
        'device_id',
        'type',
        'status',
        'version',
        'notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
