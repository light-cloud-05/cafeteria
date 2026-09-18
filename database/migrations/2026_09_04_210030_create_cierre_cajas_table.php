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
        Schema::create('cierre_cajas', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fechaInicio');
            $table->dateTime('fechaFin')->nullable();
            $table->decimal('totalSistema', 10, 2)->default(0.00);
            $table->decimal('totalDeclarado', 10, 2)->default(0.00);
            $table->decimal('diferencia', 10, 2)->default(0.00);
            $table->text('observacion')->nullable();
            
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
        Schema::dropIfExists('cierre_cajas');
    }
};