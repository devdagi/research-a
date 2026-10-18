<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 64)->index();
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'delivered', 'acknowledged', 'failed', 'expired', 'cancelled'])->default('pending')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
