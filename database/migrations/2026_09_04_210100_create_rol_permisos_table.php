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
        Schema::create('rol_permisos', function (Blueprint $table) {
            $table->id();
            
            // Relación con roles (apuntando a la clave primaria personalizada 'rol_id')
            $table->unsignedBigInteger('idRol');
            $table->foreign('idRol')->references('rol_id')->on('roles')->onDelete('cascade');
            
            // Relación con permisos
            $table->foreignId('idPermiso')->constrained('permisos')->onDelete('cascade');
            
            $table->dateTime('fechaAsignacion')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rol_permisos');
    }
};