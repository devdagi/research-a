<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->longText('device_offer_sdp')->nullable()->after('signaling_token_hash');
            $table->longText('admin_answer_sdp')->nullable()->after('device_offer_sdp');
            $table->json('device_ice')->nullable()->after('admin_answer_sdp');
            $table->json('admin_ice')->nullable()->after('device_ice');
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'device_offer_sdp',
                'admin_answer_sdp',
                'device_ice',
                'admin_ice',
            ]);
        });
    }
};
