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
        Schema::create('movimiento_inventarios', function (Blueprint $table) {
            $table->id();

            $table->string("tipo");
            $table->decimal("cantidad");
            $table->dateTime("fecha_hora");
            $table->string("motivo");

            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('proveedor_id')->constrained('proveedores');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_inventarios');
    }
};
