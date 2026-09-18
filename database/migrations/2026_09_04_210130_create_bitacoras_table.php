<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->string('tablaAfectada', 50);
            $table->unsignedBigInteger('idRegistroAfectado');
            $table->string('accion', 50);
            $table->text('valoresAnteriores')->nullable();
            $table->text('valoresNuevos')->nullable();
            $table->dateTime('fechaHora')->useCurrent();
            
            // Relación con la tabla usuarios
            $table->foreignId('idUsuario')->constrained('usuarios')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacoras');
    }
};