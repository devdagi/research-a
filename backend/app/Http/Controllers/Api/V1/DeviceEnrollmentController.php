<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceEnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollment)
    {
    }

    public function enroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_name' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:32'],
            'model' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'fcm_token' => ['nullable', 'string', 'max:512'],
        ]);

        $result = $this->enrollment->enrollOpen($data);
        $device = $result['device'];

        return response()->json([
            'data' => [
                'device' => [
                    'uuid' => $device->uuid,
                    'name' => $device->name,
                    'platform' => $device->platform,
                    'status' => $device->status,
                    'enrolled_at' => $device->enrolled_at?->toIso8601String(),
                ],
                'token' => $result['token'],
                'token_type' => 'Bearer',
            ],
        ], 201);
    }
}
