<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->decimal('cpu_percent', 5, 1)->nullable();
            $table->decimal('cpu_temp', 5, 1)->nullable();
            $table->unsignedInteger('ram_used_mb')->nullable();
            $table->unsignedInteger('ram_total_mb')->nullable();
            $table->decimal('disk_used_gb', 8, 2)->nullable();
            $table->decimal('disk_total_gb', 8, 2)->nullable();
            $table->unsignedInteger('running_projects')->default(0);
            $table->boolean('tunnel_active')->default(false);
            $table->string('firmware_version')->nullable();
            $table->string('os_version')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_heartbeats');
    }
};
