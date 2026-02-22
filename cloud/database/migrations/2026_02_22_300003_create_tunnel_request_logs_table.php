<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tunnel_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tunnel_route_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tunnel_route_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tunnel_request_logs');
    }
};
