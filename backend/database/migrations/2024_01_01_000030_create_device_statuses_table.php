<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('is_charging')->nullable();
            $table->string('network_type', 32)->nullable();
            $table->boolean('is_online')->default(true);
            $table->json('extras')->nullable();
            $table->timestamp('reported_at')->index();
            $table->timestamps();
            $table->index(['device_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_statuses');
    }
};
