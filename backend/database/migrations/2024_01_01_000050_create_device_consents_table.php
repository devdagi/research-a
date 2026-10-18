<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('type', 64)->index();
            $table->enum('status', ['granted', 'denied', 'withdrawn'])->index();
            $table->string('version', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['device_id', 'type', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_consents');
    }
};
