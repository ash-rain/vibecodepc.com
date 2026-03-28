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
        Schema::create('port_allocation_locks', function (Blueprint $table) {
            $table->id();
            $table->string('lock_key')->unique();
            $table->timestamp('locked_at');
            $table->timestamps();

            $table->index('lock_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_allocation_locks');
    }
};
