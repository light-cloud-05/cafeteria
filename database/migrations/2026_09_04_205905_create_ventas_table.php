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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
   
            $table->dateTime("fecha_hora");
            $table->decimal("total", 8, 2);
            $table->string("metodo_pago");
            $table->decimal("monto_recibido", 8, 2);
            $table->decimal("cambio", 8, 2); 
            $table->string("estado");

            $table->foreignId('pedido_id')->constrained('pedidos');
            $table->foreignId('usuario_id')->constrained('usuarios');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
