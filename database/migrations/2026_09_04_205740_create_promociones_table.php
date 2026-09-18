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
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('tipo', 50);
            $table->string('aplicaSobre', 50)->nullable();
            $table->decimal('valor', 10, 2);
            $table->decimal('topeDescuento', 10, 2)->nullable();
            $table->date('fechaInicio');
            $table->date('fechaFin');
            $table->time('horaInicio')->nullable();
            $table->time('horaFin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promociones');
    }
};