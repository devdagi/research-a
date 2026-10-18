<?php

return [
    'device_online_threshold_minutes' => (int) env('CDM_DEVICE_ONLINE_THRESHOLD_MINUTES', env('DEVICE_ONLINE_THRESHOLD_MINUTES', 5)),
    'location_retention_days' => (int) env('CDM_LOCATION_RETENTION_DAYS', env('LOCATION_RETENTION_DAYS', 90)),
    'enrollment_code_ttl_minutes' => (int) env('CDM_ENROLLMENT_CODE_TTL_MINUTES', env('ENROLLMENT_CODE_TTL_MINUTES', 60)),
    'enrollment_max_attempts' => (int) env('CDM_ENROLLMENT_MAX_ATTEMPTS', env('ENROLLMENT_MAX_ATTEMPTS', 10)),
    'command_ttl_minutes' => (int) env('CDM_COMMAND_TTL_MINUTES', env('COMMAND_TTL_MINUTES', 30)),
    'live_session_ttl_minutes' => (int) env('CDM_LIVE_SESSION_TTL_MINUTES', env('LIVE_SESSION_TTL_MINUTES', 15)),
    'turn_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('CDM_TURN_URLS', env('TURN_URLS', '')))))),
    'turn_username' => env('CDM_TURN_USERNAME', env('TURN_USERNAME')),
    'turn_credential' => env('CDM_TURN_CREDENTIAL', env('TURN_CREDENTIAL')),
    'webrtc_configured' => filter_var(env('CDM_WEBRTC_CONFIGURED', env('WEBRTC_CONFIGURED', false)), FILTER_VALIDATE_BOOL),
    'mapbox_access_token' => env('MAPBOX_ACCESS_TOKEN', ''),
    'mapbox_style' => env('MAPBOX_STYLE', 'mapbox://styles/mapbox/standard'),
];
