<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->text('content')->nullable();
            $table->string('wa_message_id', 100)->nullable();
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('contact_id');
            $table->index('wa_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
