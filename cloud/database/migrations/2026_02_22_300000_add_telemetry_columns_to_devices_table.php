<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('pairing_token_encrypted');
            $table->boolean('is_online')->default(false)->after('last_heartbeat_at');
            $table->string('os_version')->nullable()->after('is_online');
            $table->decimal('cpu_temp', 5, 1)->nullable()->after('os_version');
            $table->decimal('cpu_percent', 5, 1)->nullable()->after('cpu_temp');
            $table->unsignedInteger('ram_used_mb')->nullable()->after('cpu_percent');
            $table->unsignedInteger('ram_total_mb')->nullable()->after('ram_used_mb');
            $table->decimal('disk_used_gb', 8, 2)->nullable()->after('ram_total_mb');
            $table->decimal('disk_total_gb', 8, 2)->nullable()->after('disk_used_gb');
            $table->string('tunnel_url')->nullable()->after('disk_total_gb');

            $table->index('is_online');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex(['is_online']);
            $table->dropColumn([
                'last_heartbeat_at',
                'is_online',
                'os_version',
                'cpu_temp',
                'cpu_percent',
                'ram_used_mb',
                'ram_total_mb',
                'disk_used_gb',
                'disk_total_gb',
                'tunnel_url',
            ]);
        });
    }
};
