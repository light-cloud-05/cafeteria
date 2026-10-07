<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        if (DB::table('stocks')->select('producto_id')->groupBy('producto_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Existen stocks duplicados. Revise los registros antes de migrar.');
        }
        Schema::table('stocks',fn(Blueprint $t)=>$t->unique('producto_id'));
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE movimiento_inventarios MODIFY proveedor_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('movimiento_inventarios',fn(Blueprint $t)=>$t->unsignedBigInteger('proveedor_id')->nullable()->change());
        }
    }
    public function down(): void {
        Schema::table('stocks',fn(Blueprint $t)=>$t->dropUnique(['producto_id']));
        // Se conserva nullable para no perder movimientos sin proveedor.
    }
};
