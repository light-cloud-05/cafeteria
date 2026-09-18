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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->decimal('stock_actual', 10, 2)->default(0.00);
            $table->decimal('stock_minimo', 10, 2)->default(0.00);
            $table->dateTime('fecha_actualizacion')->nullable();
            
            // Relación con la tabla productos
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};