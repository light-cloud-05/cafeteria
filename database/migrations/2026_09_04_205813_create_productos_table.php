<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id(); // id estándar
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->decimal('precio', 8, 2);
            $table->boolean('disponible')->default(true);
            
            // Llaves foráneas estándar
            $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->onDelete('set null');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};