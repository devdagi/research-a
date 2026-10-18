<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceCommandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $commands = $device->commands()
            ->whereIn('status', [DeviceCommand::STATUS_PENDING, DeviceCommand::STATUS_DELIVERED])
            ->orderBy('created_at')
            ->get();

        $payload = [];

        foreach ($commands as $command) {
            if ($command->isExpired()) {
                $command->forceFill(['status' => DeviceCommand::STATUS_EXPIRED])->save();

                continue;
            }

            if ($command->status === DeviceCommand::STATUS_PENDING) {
                $command->forceFill([
                    'status' => DeviceCommand::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ])->save();
            }

            $payload[] = [
                'uuid' => $command->uuid,
                'type' => $command->type,
                'payload' => $command->payload,
                'status' => $command->status,
                'expires_at' => $command->expires_at?->toIso8601String(),
                'created_at' => $command->created_at?->toIso8601String(),
            ];
        }

        $device->touchLastSeen();

        return response()->json(['data' => $payload]);
    }

    public function acknowledge(Request $request, string $uuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->user();

        $data = $request->validate([
            'status' => ['required', 'string', 'in:acknowledged,failed'],
            'result' => ['nullable', 'array'],
        ]);

        /** @var DeviceCommand|null $command */
        $command = $device->commands()->where('uuid', $uuid)->first();

        if (! $command) {
            return response()->json([
                'message' => 'Command not found.',
                'error' => 'not_found',
            ], 404);
        }

        if ($command->status === DeviceCommand::STATUS_ACKNOWLEDGED) {
            return response()->json([
                'data' => [
                    'uuid' => $command->uuid,
                    'status' => $command->status,
                    'duplicate' => true,
                ],
            ]);
        }

        if ($command->isExpired()) {
            $command->forceFill(['status' => DeviceCommand::STATUS_EXPIRED])->save();

            return response()->json([
                'message' => 'Command has expired.',
                'error' => 'command_expired',
            ], 409);
        }

        $command->forceFill([
            'status' => $data['status'] === 'failed'
                ? DeviceCommand::STATUS_FAILED
                : DeviceCommand::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'payload' => array_merge($command->payload ?? [], [
                'ack_result' => $data['result'] ?? null,
            ]),
        ])->save();

        return response()->json([
            'data' => [
                'uuid' => $command->uuid,
                'status' => $command->status,
                'acknowledged_at' => $command->acknowledged_at?->toIso8601String(),
                'duplicate' => false,
            ],
        ]);
    }
}
