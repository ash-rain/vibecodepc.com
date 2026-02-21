<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status')->default('unclaimed');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paired_at')->nullable();
            $table->string('ip_hint')->nullable();
            $table->string('hardware_serial')->nullable();
            $table->string('firmware_version')->nullable();
            $table->text('pairing_token_encrypted')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
