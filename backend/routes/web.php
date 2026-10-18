<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CommandController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\LiveSessionController;
use App\Http\Controllers\Admin\MapAudioController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.login'));

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/map-audio', [MapAudioController::class, 'index'])->name('map-audio.index');
    Route::get('/map-audio/fleet-pulse', [MapAudioController::class, 'fleetPulse'])->name('map-audio.fleet-pulse');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::get('/devices/{device}/live-pulse', [DeviceController::class, 'livePulse'])->name('devices.live-pulse');
    Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke'])->name('devices.revoke');
    Route::post('/devices/{device}/commands', [DeviceController::class, 'issueCommand'])->name('devices.commands');
    Route::post('/devices/{device}/live-sessions', [DeviceController::class, 'requestLiveSession'])->name('devices.live-sessions');
    Route::post('/devices/{device}/listen-audio', [DeviceController::class, 'requestListenAudio'])->name('devices.listen-audio');
    Route::post('/devices/{device}/live-sessions/{liveSession}/answer', [DeviceController::class, 'postAdminAnswer'])->name('devices.live-answer');
    Route::post('/devices/{device}/live-sessions/{liveSession}/ice', [DeviceController::class, 'postAdminIce'])->name('devices.live-ice');

    Route::get('/commands', [CommandController::class, 'index'])->name('commands.index');
    Route::get('/live-sessions', [LiveSessionController::class, 'index'])->name('live-sessions.index');
    Route::post('/live-sessions/{liveSession}/cancel', [LiveSessionController::class, 'cancel'])->name('live-sessions.cancel');

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
