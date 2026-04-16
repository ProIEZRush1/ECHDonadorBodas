<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->nullable();
            $table->string('apellido_paterno', 100)->nullable();
            $table->string('nombre_completo', 300)->nullable();
            $table->string('telefono', 20)->unique();
            $table->string('wa_id', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->enum('status', [
                'nuevo',
                'contactado',
                'leido',
                'interesado',
                'datos_enviados',
                'donador',
                'no_interesado',
            ])->default('nuevo');
            $table->unsignedInteger('boletos')->default(0);
            $table->json('datos_extra')->nullable();
            $table->text('notas')->nullable();
            $table->string('pais', 5)->nullable();
            $table->string('ultimo_mensaje_status', 20)->nullable();
            $table->timestamp('ultimo_contacto')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('wa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
