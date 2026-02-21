<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('framework');
            $table->string('status')->default('created');
            $table->string('path');
            $table->integer('port')->nullable();
            $table->string('container_id')->nullable();
            $table->string('tunnel_subdomain_path')->nullable();
            $table->boolean('tunnel_enabled')->default(false);
            $table->json('env_vars')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_stopped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
