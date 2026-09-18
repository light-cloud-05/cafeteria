<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id(); // id estándar
            $table->string('nombreCompleto');
            $table->string('nombreUsuario')->unique();
            $table->string('password');
            $table->string('telefono')->nullable();
            $table->boolean('estado')->default(true);
            
            // Llave foránea estándar
            $table->foreignId('rol_id')->constrained('roles','rol_id')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};