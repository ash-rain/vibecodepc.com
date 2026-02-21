<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tunnel_configs', function (Blueprint $table) {
            $table->id();
            $table->string('subdomain');
            $table->text('tunnel_token_encrypted')->nullable();
            $table->string('tunnel_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tunnel_configs');
    }
};
