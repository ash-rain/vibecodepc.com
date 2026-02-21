<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_credentials', function (Blueprint $table) {
            $table->id();
            $table->text('pairing_token_encrypted');
            $table->string('cloud_username')->nullable();
            $table->string('cloud_email')->nullable();
            $table->string('cloud_url');
            $table->boolean('is_paired')->default(false);
            $table->timestamp('paired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_credentials');
    }
};
