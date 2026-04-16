<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_step', 50)->default('inicio');
            $table->json('collected_data')->nullable();
            $table->json('ai_context')->nullable();
            $table->timestamp('last_interaction')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_states');
    }
};
