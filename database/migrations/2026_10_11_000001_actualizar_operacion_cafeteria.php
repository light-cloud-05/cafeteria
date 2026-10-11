<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('usuarios',function(Blueprint $t){$t->softDeletes();$t->string('session_token',64)->nullable();});
        Schema::table('promociones',fn(Blueprint $t)=>$t->json('producto_ids')->nullable());
        Schema::table('ventas',function(Blueprint $t){$t->decimal('monto_qr',8,2)->default(0);$t->decimal('monto_efectivo',8,2)->default(0);});
        DB::table('ventas')->whereIn('metodo_pago',['QR','Transferencia QR'])->update(['monto_qr'=>DB::raw('total')]);
        DB::table('ventas')->where('metodo_pago','Efectivo')->update(['monto_efectivo'=>DB::raw('total')]);
        Schema::table('pedidos',function(Blueprint $t){$t->date('fecha_numero')->nullable();$t->unsignedInteger('numero_diario')->nullable();$t->boolean('stock_reservado')->default(false);$t->unique(['fecha_numero','numero_diario']);});
        Schema::create('numeradores_pedidos',function(Blueprint $t){$t->date('fecha')->primary();$t->unsignedInteger('ultimo')->default(0);});
        foreach(DB::table('pedidos')->orderBy('fechaHora')->orderBy('id')->get() as $p){
            $fecha=\Illuminate\Support\Carbon::parse($p->fechaHora,'UTC')->setTimezone('America/La_Paz')->toDateString();
            DB::table('numeradores_pedidos')->insertOrIgnore(['fecha'=>$fecha,'ultimo'=>0]);
            DB::table('numeradores_pedidos')->where('fecha',$fecha)->increment('ultimo');
            DB::table('pedidos')->where('id',$p->id)->update(['fecha_numero'=>$fecha,'numero_diario'=>DB::table('numeradores_pedidos')->where('fecha',$fecha)->value('ultimo')]);
        }
        // Las reservas antiguas se verifican al cobrar; no se altera stock histórico.
    }
    public function down(): void {
        Schema::dropIfExists('numeradores_pedidos');
        Schema::table('pedidos',function(Blueprint $t){$t->dropUnique(['fecha_numero','numero_diario']);$t->dropColumn(['fecha_numero','numero_diario','stock_reservado']);});
        Schema::table('ventas',fn(Blueprint $t)=>$t->dropColumn(['monto_qr','monto_efectivo']));
        Schema::table('promociones',fn(Blueprint $t)=>$t->dropColumn('producto_ids'));
        Schema::table('usuarios',fn(Blueprint $t)=>$t->dropColumn(['deleted_at','session_token']));
    }
};
