<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->nullable();
            $table->unsignedInteger('boletos')->default(1);
            $table->string('reference', 100)->nullable();
            $table->string('receipt_media_id', 100)->nullable();
            $table->json('receipt_analysis')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->enum('confidence', ['high', 'medium', 'low'])->default('low');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
