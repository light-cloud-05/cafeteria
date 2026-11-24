<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $t) {
            $t->decimal('monto_tarjeta', 8, 2)->default(0);
            $t->enum('origen', ['OPERACION', 'SIMULACION_LOCAL'])->default('OPERACION');
        });
        Schema::table('cierre_caja_versiones', fn (Blueprint $t) => $t->decimal('tarjeta_registrado', 16, 2)->default(0));
        Schema::table('cierre_caja_version_ventas', function (Blueprint $t) {
            $t->decimal('monto_tarjeta', 16, 2)->default(0);
            $t->enum('origen', ['OPERACION', 'SIMULACION_LOCAL'])->default('OPERACION');
        });
    }

    public function down(): void
    {
        // No perder trazabilidad ni componentes de importes ya persistidos.
        if (DB::table('ventas')->where('monto_tarjeta', '<>', 0)->orWhere('origen', 'SIMULACION_LOCAL')->exists()
            || DB::table('cierre_caja_versiones')->where('tarjeta_registrado', '<>', 0)->exists()
            || DB::table('cierre_caja_version_ventas')->where('monto_tarjeta', '<>', 0)->orWhere('origen', 'SIMULACION_LOCAL')->exists()) {
            throw new RuntimeException('No se puede retirar tarjeta/origen con historia de pagos simulados.');
        }
        Schema::table('cierre_caja_version_ventas', fn (Blueprint $t) => $t->dropColumn(['monto_tarjeta', 'origen']));
        Schema::table('cierre_caja_versiones', fn (Blueprint $t) => $t->dropColumn('tarjeta_registrado'));
        Schema::table('ventas', fn (Blueprint $t) => $t->dropColumn(['monto_tarjeta', 'origen']));
    }
};
