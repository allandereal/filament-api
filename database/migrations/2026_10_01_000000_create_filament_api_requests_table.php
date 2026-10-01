<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filament_api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('panel');
            $table->string('method', 10);
            $table->string('path');
            $table->json('query')->nullable();
            $table->string('route_name')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('action')->nullable();
            $table->string('record_key')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->nullableMorphs('user');
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('token_name')->nullable();
            $table->string('tenant_key')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['panel', 'created_at']);
            $table->index(['panel', 'endpoint']);
            $table->index('status_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filament_api_requests');
    }
};
