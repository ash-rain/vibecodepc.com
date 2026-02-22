<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite requires recreating the table to change constraints.
        // Use raw SQL for the index drop to handle naming differences.
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, rebuild via Schema (it handles the nuance internally)
            Schema::table('tunnel_routes', function (Blueprint $table) {
                $table->unique(['subdomain', 'path', 'project_name'], 'tunnel_routes_subdomain_path_project_unique');
            });
        } else {
            Schema::table('tunnel_routes', function (Blueprint $table) {
                $table->dropUnique(['subdomain', 'path']);
                $table->unique(['subdomain', 'path', 'project_name'], 'tunnel_routes_subdomain_path_project_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tunnel_routes', function (Blueprint $table) {
            $table->dropUnique('tunnel_routes_subdomain_path_project_unique');

            if (DB::getDriverName() !== 'sqlite') {
                $table->unique(['subdomain', 'path']);
            }
        });
    }
};
