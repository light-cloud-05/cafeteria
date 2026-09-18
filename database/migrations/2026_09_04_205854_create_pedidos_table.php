<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id(); // id estándar
            $table->dateTime('fechaHora');
            $table->decimal('total', 8, 2);
            $table->string('estado');
            
            // Llave foránea estándar
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};