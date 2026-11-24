<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transacciones_pago', function (Blueprint $t) {
            $t->engine = 'InnoDB';
            $t->id();
            $t->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $t->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $t->foreignId('venta_id')->nullable()->unique()->constrained('ventas')->restrictOnDelete();
            $t->uuid('referencia')->unique();
            $t->uuid('clave_idempotencia')->unique();
            $t->enum('medio', ['QR_SIMULADO', 'TARJETA_SIMULADA']);
            $t->enum('origen', ['SIMULACION_LOCAL'])->default('SIMULACION_LOCAL');
            $t->decimal('monto', 8, 2);
            $t->enum('moneda', ['BOB'])->default('BOB');
            $t->enum('estado', ['PENDIENTE', 'APROBADO', 'RECHAZADO', 'EXPIRADO', 'CANCELADO']);
            $t->char('version_pedido', 64);
            $t->dateTime('vence_en'); // UTC, como el resto de fechas financieras.
            $t->dateTime('confirmado_en')->nullable();
            $t->dateTime('resuelto_en')->nullable();
            $t->string('codigo_resultado', 40)->nullable(); // Lista cerrada; sin payloads ni datos de tarjeta.
            // NULL permite varios terminales; la unicidad impide dos pendientes incluso por SQL.
            $t->unsignedBigInteger('pedido_activo_id')->nullable()
                ->storedAs("CASE WHEN estado = 'PENDIENTE' THEN pedido_id ELSE NULL END")->unique();
            $t->timestamps();
            $t->index(['pedido_id', 'created_at']);
            $t->index(['estado', 'vence_en']);
        });
    }

    public function down(): void
    {
        if (DB::table('transacciones_pago')->exists()) {
            throw new RuntimeException('No se puede eliminar el historial de intentos de pago.');
        }
        Schema::dropIfExists('transacciones_pago');
    }
};
