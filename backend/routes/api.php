<?php

use App\Http\Controllers\Api\V1\DeviceCommandController;
use App\Http\Controllers\Api\V1\DeviceConsentController;
use App\Http\Controllers\Api\V1\DeviceEnrollmentController;
use App\Http\Controllers\Api\V1\DeviceHeartbeatController;
use App\Http\Controllers\Api\V1\DeviceLocationController;
use App\Http\Controllers\Api\V1\DeviceProfileController;
use App\Http\Controllers\Api\V1\DeviceStatusController;
use App\Http\Controllers\Api\V1\LiveSessionDeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/device/enroll', [DeviceEnrollmentController::class, 'enroll'])
        ->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'device'])->prefix('device')->group(function () {
        Route::get('/me', [DeviceProfileController::class, 'show']);
        Route::post('/unenroll', [DeviceProfileController::class, 'unenroll']);
        Route::post('/heartbeat', [DeviceHeartbeatController::class, 'store'])
            ->middleware('throttle:60,1');
        Route::post('/status', [DeviceStatusController::class, 'store'])
            ->middleware('throttle:60,1');
        Route::post('/location', [DeviceLocationController::class, 'store'])
            ->middleware('throttle:120,1');
        Route::get('/consents', [DeviceConsentController::class, 'index']);
        Route::post('/consents', [DeviceConsentController::class, 'store']);
        Route::get('/commands', [DeviceCommandController::class, 'index']);
        Route::post('/commands/{uuid}/ack', [DeviceCommandController::class, 'acknowledge']);
        Route::get('/live-sessions/pending', [LiveSessionDeviceController::class, 'pending']);
        Route::post('/live-sessions/{uuid}/respond', [LiveSessionDeviceController::class, 'respond']);
        Route::post('/live-sessions/{uuid}/start', [LiveSessionDeviceController::class, 'start']);
        Route::post('/live-sessions/{uuid}/end', [LiveSessionDeviceController::class, 'end']);
        Route::post('/live-sessions/{uuid}/offer', [LiveSessionDeviceController::class, 'postOffer']);
        Route::post('/live-sessions/{uuid}/ice', [LiveSessionDeviceController::class, 'postDeviceIce']);
        Route::get('/live-sessions/{uuid}/signaling', [LiveSessionDeviceController::class, 'signaling']);
    });
});
