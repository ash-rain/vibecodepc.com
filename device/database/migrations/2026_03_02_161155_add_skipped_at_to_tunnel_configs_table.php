<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tunnel_configs', function (Blueprint $table) {
            $table->timestamp('skipped_at')->nullable()->after('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tunnel_configs', function (Blueprint $table) {
            $table->dropColumn('skipped_at');
        });
    }
};
