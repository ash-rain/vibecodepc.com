<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_credentials', function (Blueprint $table) {
            $table->id();
            $table->text('access_token_encrypted');
            $table->string('github_username');
            $table->string('github_email')->nullable();
            $table->string('github_name')->nullable();
            $table->boolean('has_copilot')->default(false);
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_credentials');
    }
};
