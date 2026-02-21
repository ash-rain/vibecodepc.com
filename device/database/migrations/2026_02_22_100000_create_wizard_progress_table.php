<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizard_progress', function (Blueprint $table) {
            $table->id();
            $table->string('step')->unique();
            $table->string('status')->default('pending');
            $table->json('data_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wizard_progress');
    }
};
