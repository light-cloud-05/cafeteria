<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        if (DB::table('ventas')->select('pedido_id')->groupBy('pedido_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Hay ventas duplicadas por pedido. Revise los registros antes de migrar.');
        }
        Schema::table('ventas',fn(Blueprint $t)=>$t->unique('pedido_id'));
        Schema::table('promociones',fn(Blueprint $t)=>$t->foreignId('producto_id')->nullable()->constrained('productos'));
        Schema::table('pedidos',function(Blueprint $t) {
            $t->decimal('subtotal',8,2)->default(0);
            $t->decimal('descuento',8,2)->default(0);
            $t->foreignId('promocion_id')->nullable()->constrained('promociones');
        });
        DB::table('pedidos')->update(['subtotal'=>DB::raw('total')]);
    }
    public function down(): void {
        Schema::table('pedidos',function(Blueprint $t) { $t->dropConstrainedForeignId('promocion_id'); $t->dropColumn(['subtotal','descuento']); });
        Schema::table('promociones',fn(Blueprint $t)=>$t->dropConstrainedForeignId('producto_id'));
        Schema::table('ventas',fn(Blueprint $t)=>$t->dropUnique(['pedido_id']));
    }
};
