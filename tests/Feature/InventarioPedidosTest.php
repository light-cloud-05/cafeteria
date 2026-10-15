<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\{Schema,DB};
use Illuminate\Database\Schema\Blueprint;
use App\Models\{User,Producto,Stock,Pedido,MovimientoInventario,Bitacora,Venta,Promocion};
class InventarioPedidosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);
        DB::purge('sqlite');
        foreach(['categorias','productos','stocks','proveedores','usuarios','pedidos','detalle_pedidos','movimiento_inventarios','bitacoras','ventas','promociones'] as $table) {
            Schema::create($table,function(Blueprint $t) use($table) {
                $t->id();$t->timestamps();
                if(in_array($table,['categorias','productos','proveedores','promociones'])) $t->string('nombre');
                if(in_array($table,['categorias','proveedores','usuarios'])) $t->boolean('estado')->default(true);
                if($table==='usuarios') {$t->string('nombreCompleto');$t->string('nombreUsuario')->nullable();$t->string('password')->nullable();$t->softDeletes();$t->string('session_token',64)->nullable();$t->unsignedBigInteger('session_last_activity')->nullable();}
                if($table==='productos') {$t->decimal('precio',8,2);$t->boolean('disponible');$t->integer('categoria_id');$t->integer('proveedor_id')->nullable();}
                if($table==='stocks') {$t->integer('producto_id')->unique();$t->decimal('stock_actual',10,2);$t->decimal('stock_minimo',10,2);$t->dateTime('fecha_actualizacion')->nullable();}
                if($table==='pedidos') {$t->date('fecha_numero')->nullable();$t->integer('numero_diario')->nullable();$t->boolean('stock_reservado')->default(false);$t->integer('usuario_id');$t->dateTime('fechaHora');$t->decimal('total',8,2);$t->decimal('subtotal',8,2)->default(0);$t->decimal('descuento',8,2)->default(0);$t->integer('promocion_id')->nullable();$t->string('estado');}
                if($table==='detalle_pedidos') {$t->integer('pedido_id');$t->integer('producto_id');$t->integer('promocion_id')->nullable();$t->integer('cantidad');$t->string('opcion');$t->decimal('precioUnitario',8,2);$t->decimal('subtotal',8,2);}
                if($table==='ventas') {$t->decimal('monto_qr',8,2)->default(0);$t->decimal('monto_efectivo',8,2)->default(0);$t->integer('pedido_id')->unique();$t->integer('usuario_id');$t->dateTime('fecha_hora');$t->decimal('total',8,2);$t->string('metodo_pago');$t->decimal('monto_recibido',8,2);$t->decimal('cambio',8,2);$t->string('estado');}
                if($table==='promociones') {$t->json('producto_ids')->nullable();$t->string('tipo');$t->string('aplicaSobre');$t->decimal('valor',10,2);$t->decimal('topeDescuento',10,2)->nullable();$t->date('fechaInicio');$t->date('fechaFin');$t->time('horaInicio')->nullable();$t->time('horaFin')->nullable();$t->boolean('activo');$t->integer('producto_id')->nullable();}
                if($table==='movimiento_inventarios') {$t->string('tipo');$t->decimal('cantidad',10,2);$t->dateTime('fecha_hora');$t->string('motivo');$t->integer('usuario_id');$t->integer('producto_id');$t->integer('proveedor_id')->nullable();}
                if($table==='bitacoras') {$t->string('tablaAfectada');$t->integer('idRegistroAfectado');$t->string('accion');$t->text('valoresAnteriores')->nullable();$t->text('valoresNuevos')->nullable();$t->dateTime('fechaHora');$t->integer('idUsuario');$t->string('ip',45)->nullable();}
            });
        }
        Schema::create('numeradores_pedidos',function(Blueprint $t){$t->date('fecha')->primary();$t->integer('ultimo')->default(0);});
        $user = new class extends User { public function tienePermiso(string $nombre): bool { return true; } };
        $user->forceFill(['id'=>1,'nombreCompleto'=>'Prueba','estado'=>1]);$user->exists=true;
        DB::table('usuarios')->insert(['id'=>1,'nombreCompleto'=>'Prueba','estado'=>1]);$this->actingAs($user);
        DB::table('categorias')->insert(['id'=>1,'nombre'=>'Bebidas','estado'=>1]);
        Producto::create(['nombre'=>'Café','precio'=>'12.35','disponible'=>true,'categoria_id'=>1]);
        Stock::create(['producto_id'=>1,'stock_actual'=>10,'stock_minimo'=>2]);
    }
    private function movimiento(string $tipo, float $cantidad)
    {
        return $this->post('/admin/inventario/'.$tipo,['producto_id'=>1,'cantidad'=>$cantidad,'motivo'=>'Conteo de prueba','fecha_hora'=>now()->format('Y-m-d H:i:s')]);
    }
    public function test_definir_stock_actual_desde_consulta_crea_stock_y_registra_diferencia(): void
    {
        $this->get('/admin/stocks')->assertOk()->assertSee('Guardar stock actual');
        $datos = ['producto_id'=>1,'cantidad'=>25,'motivo'=>'Conteo físico','fecha_hora'=>now()->format('Y-m-d H:i:s'),'volver_stock'=>1];
        $this->post('/admin/inventario/ajuste',$datos)->assertSessionHasNoErrors()->assertRedirect('/admin/stocks');
        $this->assertEquals(25,Stock::first()->stock_actual);
        $this->assertEquals(15,MovimientoInventario::first()->cantidad);
        $this->post('/admin/inventario/ajuste',array_replace($datos,['cantidad'=>-1]))->assertSessionHasErrors('cantidad');
        $this->assertEquals(25,Stock::first()->stock_actual);
        $producto = Producto::create(['nombre'=>'Té','precio'=>5,'disponible'=>true,'categoria_id'=>1]);
        $this->post('/admin/inventario/ajuste',array_replace($datos,['producto_id'=>$producto->id,'cantidad'=>8]))->assertSessionHasNoErrors();
        $this->assertEquals(8,Stock::where('producto_id',$producto->id)->first()->stock_actual);
        $this->assertEquals(2,MovimientoInventario::count());
    }
    public function test_entrada_salida_y_ajuste_actualizan_stock_y_auditan_transiciones(): void
    {
        $this->movimiento('entrada',5)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(15,Stock::first()->stock_actual);
        $this->movimiento('salida',13)->assertSessionHasNoErrors();
        $this->assertEquals(2,Stock::first()->stock_actual);
        $this->assertEquals(1,Bitacora::where('accion','PRODUCTO_AGOTADO')->count());
        $this->movimiento('ajuste',1)->assertSessionHasNoErrors();
        $this->assertEquals(1,Bitacora::where('accion','PRODUCTO_AGOTADO')->count());
        $this->movimiento('ajuste',1)->assertSessionHasNoErrors();
        $this->assertEquals(3,MovimientoInventario::count());
        $this->movimiento('entrada',5)->assertSessionHasNoErrors();
        $this->assertEquals(1,Bitacora::where('accion','PRODUCTO_REPUESTO')->count());
    }
    public function test_salida_insuficiente_no_escribe_movimientos(): void
    {
        $this->movimiento('salida',11)->assertSessionHasErrors('cantidad');
        $this->assertEquals(10,Stock::first()->stock_actual);$this->assertEquals(0,MovimientoInventario::count());
        Producto::first()->update(['disponible'=>false]);
        $this->movimiento('salida',1)->assertSessionHasErrors('producto_id');
    }
    public function test_pedido_calcula_total_y_rechaza_agotados_y_duplicados(): void
    {
        $linea=['producto_id'=>1,'cantidad'=>3];
        $this->post('/admin/pedidos/nuevo',['detalles'=>[$linea],'total'=>0,'usuario_id'=>999])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(37.05,Pedido::first()->total);$this->assertEquals(1,Pedido::first()->usuario_id);
        $this->assertEquals('Registrado',Pedido::first()->estado);
        $this->post('/admin/pedidos/nuevo',['detalles'=>[$linea,$linea]])->assertSessionHasErrors();
        Stock::first()->update(['stock_actual'=>2]);
        $this->post('/admin/pedidos/nuevo',['detalles'=>[$linea]])->assertSessionHasErrors('detalles.0.cantidad');
        $this->assertEquals(1,Pedido::count());
    }
    private function nuevoPedido(int $cantidad=2, ?int $promocion=null): Pedido
    {
        $this->post('/admin/pedidos/nuevo',['detalles'=>[['producto_id'=>1,'cantidad'=>$cantidad]],'promocion_id'=>$promocion])->assertSessionHasNoErrors();
        return Pedido::latest('id')->first();
    }
    public function test_modificacion_cancelacion_y_transiciones_validas(): void
    {
        $pedido=$this->nuevoPedido();
        $this->get('/admin/pedidos/editar/'.$pedido->id)->assertOk()->assertSee('Modificar pedido');
        $this->put('/admin/pedidos/editar/'.$pedido->id,['detalles'=>[['producto_id'=>1,'cantidad'=>4]]])->assertSessionHasNoErrors();
        $this->assertEquals(49.40,$pedido->fresh()->total);
        $this->assertEquals(1,$pedido->detalles()->count());
        $this->put('/admin/pedidos/estado/'.$pedido->id,['estado'=>'Completado'])->assertSessionHasErrors('estado');
        $this->put('/admin/pedidos/estado/'.$pedido->id,['estado'=>'Pagado'])->assertSessionHasErrors('estado');
        $this->post('/admin/pedidos/cancelar/'.$pedido->id)->assertSessionHasNoErrors();
        $this->assertEquals('Cancelado',$pedido->fresh()->estado);
        $this->assertEquals(10,Stock::first()->stock_actual);
        $this->put('/admin/pedidos/editar/'.$pedido->id,['detalles'=>[['producto_id'=>1,'cantidad'=>1]]])->assertSessionHasErrors('pedido');
        $this->post('/admin/ventas/cobrar/'.$pedido->id,['version_pedido'=>$pedido->fresh()->versionCobro(),'metodo_pago'=>'QR'])->assertSessionHasErrors('pedido');
    }
    public function test_cobro_efectivo_calcula_cambio_descuenta_stock_y_no_se_duplica(): void
    {
        $pedido=$this->nuevoPedido(3);
        $this->get('/admin/ventas/cobrar/'.$pedido->id)->assertOk();
        $this->post('/admin/ventas/cobrar/'.$pedido->id,['version_pedido'=>$pedido->fresh()->versionCobro(),'metodo_pago'=>'Efectivo','monto_recibido'=>20])->assertSessionHasErrors('monto_recibido');
        $this->assertEquals(7,Stock::first()->stock_actual);$this->assertEquals(0,Venta::count());
        $pago=['version_pedido'=>$pedido->fresh()->versionCobro(),'metodo_pago'=>'Efectivo','monto_recibido'=>50,'total'=>0,'cambio'=>500];
        $this->post('/admin/ventas/cobrar/'.$pedido->id,$pago)->assertSessionHasNoErrors();
        $this->assertEquals(37.05,Venta::first()->total);$this->assertEquals(12.95,Venta::first()->cambio);
        $this->assertEquals(7,Stock::first()->stock_actual);$this->assertEquals('Pagado',$pedido->fresh()->estado);
        $this->post('/admin/ventas/cobrar/'.$pedido->id,$pago)->assertSessionHasErrors('pedido');
        $this->assertEquals(1,Venta::count());$this->assertEquals(1,MovimientoInventario::count());
        $this->put('/admin/pedidos/editar/'.$pedido->id,['detalles'=>[['producto_id'=>1,'cantidad'=>1]]])->assertSessionHasErrors('pedido');
        $this->post('/admin/pedidos/cancelar/'.$pedido->id)->assertSessionHasErrors('pedido');
        $this->put('/admin/pedidos/estado/'.$pedido->id,['estado'=>'Completado'])->assertSessionHasNoErrors();
        $this->assertEquals('Completado',$pedido->fresh()->estado);
        $this->put('/admin/pedidos/estado/'.$pedido->id,['estado'=>'Registrado'])->assertSessionHasErrors('estado');
    }
    public function test_pago_qr_confirma_reserva_sin_descontar_otra_vez(): void
    {
        $pedido=$this->nuevoPedido();
        $this->assertEquals(8,Stock::first()->stock_actual);
        $this->post('/admin/ventas/cobrar/'.$pedido->id,['version_pedido'=>$pedido->versionCobro(),'metodo_pago'=>'QR'])->assertSessionHasNoErrors();
        $this->assertEquals(8,Stock::first()->stock_actual);
        $this->assertEquals(24.70,Venta::first()->monto_qr);
        $this->assertEquals(1,MovimientoInventario::count());
    }
    private function datosPromocion(): array
    {
        return ['nombre'=>'Oferta prueba','tipo'=>'Porcentaje','aplicaSobre'=>'Pedido','valor'=>10,
            'fechaInicio'=>now()->subDay()->toDateString(),'fechaFin'=>now()->addDay()->toDateString(),'activo'=>1];
    }
    public function test_promociones_validan_vigencia_porcentaje_y_aplican_descuento_con_tope(): void
    {
        $datos=$this->datosPromocion();
        $this->post('/admin/promociones/crear/store',array_replace($datos,['valor'=>101]))->assertSessionHasErrors('valor');
        $this->post('/admin/promociones/crear/store',array_replace($datos,['fechaFin'=>now()->subDays(2)->toDateString()]))->assertSessionHasErrors('fechaFin');
        $this->post('/admin/promociones/crear/store',array_replace($datos,['horaInicio'=>'18:00','horaFin'=>'08:00']))->assertSessionHasErrors('horaFin');
        $this->post('/admin/promociones/crear/store',$datos+['topeDescuento'=>2])->assertSessionHasNoErrors();
        $promo=Promocion::first();$pedido=$this->nuevoPedido(3,$promo->id);
        $this->assertEquals(2,$pedido->descuento);$this->assertEquals(35.05,$pedido->total);
        $this->delete('/admin/promociones/eliminar/'.$promo->id)->assertSessionHasNoErrors();
        $this->post('/admin/pedidos/nuevo',['detalles'=>[['producto_id'=>1,'cantidad'=>1]],'promocion_id'=>$promo->id])->assertSessionHasErrors('promocion_id');
        $this->post('/admin/promociones/activar/'.$promo->id)->assertSessionHasNoErrors();
        $promo->update(['horaInicio'=>now()->addHour()->format('H:i:s'),'horaFin'=>now()->addHours(2)->format('H:i:s')]);
        $this->assertFalse($promo->fresh()->vigente());
    }
    public function test_promocion_producto_no_descuenta_otras_lineas_y_advierte_sin_stock(): void
    {
        $otro=Producto::create(['nombre'=>'Té','precio'=>5,'disponible'=>true,'categoria_id'=>1]);
        Stock::create(['producto_id'=>$otro->id,'stock_actual'=>10,'stock_minimo'=>0]);
        $datos=array_replace($this->datosPromocion(),['aplicaSobre'=>'Producto','producto_id'=>1,'tipo'=>'Monto fijo','valor'=>50]);
        $this->post('/admin/promociones/crear/store',$datos)->assertSessionHasNoErrors();$promo=Promocion::first();
        $this->post('/admin/pedidos/nuevo',['detalles'=>[['producto_id'=>1,'cantidad'=>1],['producto_id'=>$otro->id,'cantidad'=>1]],'promocion_id'=>$promo->id])->assertSessionHasNoErrors();
        $pedido=Pedido::first();$this->assertEquals(12.35,$pedido->descuento);$this->assertEquals(5,$pedido->total);
        Stock::where('producto_id',1)->update(['stock_actual'=>0]);
        $this->post('/admin/promociones/editar/'.$promo->id,$datos)->assertSessionHas('error');
    }
    public function test_historial_reportes_y_permisos(): void
    {
        $pedido=$this->nuevoPedido();
        $this->post('/admin/ventas/cobrar/'.$pedido->id,['version_pedido'=>$pedido->fresh()->versionCobro(),'metodo_pago'=>'QR'])->assertSessionHasNoErrors();
        $this->get('/admin/pedidos?estado=Pagado&fecha='.now()->toDateString())->assertOk()->assertSee('24.70');
        $this->get('/admin/pedidos?estado=Cancelado')->assertSee('No se encontraron pedidos');
        $this->get('/admin/reportes/ventas-diarias?fecha='.now()->toDateString())->assertOk()->assertViewHas('total',24.70)->assertViewHas('cantidad',1);
        $this->get('/admin/reportes/metodos-pago')->assertOk()->assertSee('QR')->assertSee('24.70');
        $this->get('/admin/reportes/metodos-pago?desde=2026-10-10&hasta=2026-10-01')->assertSessionHasErrors('hasta');
        $this->get('/admin/reportes/inventario?modo=movimientos&producto_id=1')->assertOk()->assertSee('Reserva del pedido');
        foreach(['/admin/ventas','/admin/pedidos/ver/'.$pedido->id,'/admin/reportes/inventario','/admin/promociones','/admin/promociones/crear','/admin/reportes/ventas-diarias?fecha=2000-01-01'] as $url) $this->get($url)->assertOk();
        $this->get('/admin/reportes/ventas-diarias?fecha=incorrecta')->assertSessionHasErrors('fecha');
        $denegado=new class extends User { public function tienePermiso(string $nombre): bool { return false; } };$denegado->forceFill(['id'=>1,'estado'=>1]);$this->actingAs($denegado);
        foreach(['/admin/ventas','/admin/pedidos','/admin/reportes/inventario','/admin/promociones','/admin/pedidos/ver/'.$pedido->id] as $url) $this->get($url)->assertForbidden();
    }
    public function test_bitacora_filtra_fecha_usuario_accion_y_muestra_detalle(): void
    {
        DB::table('usuarios')->where('id',1)->update(['nombreUsuario'=>'auditor']);
        $evento=Bitacora::create(['tablaAfectada'=>'pedidos','idRegistroAfectado'=>123,'accion'=>'EVENTO_PRUEBA',
            'valoresAnteriores'=>json_encode(['estado'=>'Registrado']),'valoresNuevos'=>json_encode(['estado'=>'Pagado']),
            'fechaHora'=>'2026-10-06 15:30:00','idUsuario'=>1,'ip'=>'127.0.0.1']);
        Bitacora::create(['tablaAfectada'=>'pedidos','idRegistroAfectado'=>124,'accion'=>'EVENTO_PRUEBA','fechaHora'=>'2026-10-05 10:00:00','idUsuario'=>1]);
        $this->get('/admin/bitacora?desde=2026-10-06&hasta=2026-10-06&usuario=auditor&accion=EVENTO_PRUEBA')
            ->assertOk()->assertViewHas('bitacoras',fn($lista)=>$lista->total()===1)->assertSee('127.0.0.1');
        $this->get('/admin/bitacora/'.$evento->id)->assertOk()->assertSee('Registrado')->assertSee('Pagado')->assertSee('127.0.0.1');
        $this->get('/admin/bitacora?usuario=noexiste')->assertOk()->assertSee('Sin coincidencias');
        $this->get('/admin/bitacora?desde=2026-10-10&hasta=2026-10-01')->assertSessionHasErrors('hasta');
        $this->get('/admin/bitacora?desde=incorrecta')->assertSessionHasErrors('desde');
        $this->get('/admin/bitacora/999999')->assertNotFound();
        $this->put('/admin/bitacora/'.$evento->id,[])->assertStatus(405);
        $this->delete('/admin/bitacora/'.$evento->id)->assertStatus(405);
        Bitacora::query()->delete();$this->get('/admin/bitacora')->assertOk()->assertSee('No hay eventos registrados');
        $denegado=new class extends User { public function tienePermiso(string $nombre): bool { return false; } };$denegado->forceFill(['id'=>1,'estado'=>1]);$this->actingAs($denegado);
        $this->get('/admin/bitacora')->assertForbidden();$this->get('/admin/bitacora/'.$evento->id)->assertForbidden();
    }
    public function test_bitacora_registra_cambios_ip_sin_contrasenas_y_revierte_con_transaccion(): void
    {
        $producto=Producto::first();$producto->update(['nombre'=>'Café actualizado']);
        $evento=Bitacora::where('tablaAfectada','productos')->where('accion','ACTUALIZAR')->latest('id')->first();
        $this->assertEquals('Café',json_decode($evento->valoresAnteriores,true)['nombre']);
        $this->assertEquals('Café actualizado',json_decode($evento->valoresNuevos,true)['nombre']);
        User::first()->update(['password'=>'secreto_no_publicar']);
        $this->assertEquals(1,Bitacora::where('accion','CAMBIO_CONTRASENA')->count());
        $this->assertStringNotContainsString('secreto_no_publicar',Bitacora::all()->toJson());
        $antes=Bitacora::count();DB::beginTransaction();$producto->update(['nombre'=>'Temporal']);DB::rollBack();
        $this->assertEquals($antes,Bitacora::count());
        $this->movimiento('ajuste',20)->assertSessionHasNoErrors();
        $this->assertEquals('127.0.0.1',Bitacora::where('tablaAfectada','movimiento_inventarios')->latest('id')->first()->ip);
        $pedido=$this->nuevoPedido();$this->assertTrue(Bitacora::where('tablaAfectada','pedidos')->where('idRegistroAfectado',$pedido->id)->where('accion','CREAR')->exists());
    }
    public function test_rechaza_dos_unidades_si_solo_hay_una_antes_de_cobrar(): void
    {
        Stock::first()->update(['stock_actual'=>1,'stock_minimo'=>0]);
        $this->post('/admin/pedidos/nuevo',['detalles'=>[['producto_id'=>1,'cantidad'=>2]]])->assertSessionHasErrors('detalles.0.cantidad');
        $this->assertEquals(0,Pedido::count());$this->assertEquals(0,Venta::count());
    }
    public function test_cajero_corrige_pedido_sin_mesero_y_cobra_el_total_actualizado(): void
    {
        Producto::first()->update(['precio'=>15]);$pedido=$this->nuevoPedido(2);
        $versionAnterior=$pedido->versionCobro();$this->assertEquals(30,$pedido->total);
        Stock::first()->update(['stock_actual'=>1,'stock_minimo'=>0]);
        $otro=Producto::create(['nombre'=>'Alternativa','precio'=>8,'disponible'=>true,'categoria_id'=>1]);
        Stock::create(['producto_id'=>$otro->id,'stock_actual'=>10,'stock_minimo'=>0]);
        DB::table('usuarios')->insert(['id'=>2,'nombreCompleto'=>'Cajero','estado'=>1]);
        $cajero=new class extends User { public function tienePermiso(string $nombre): bool { return $nombre === 'cobrar_ventas'; } };
        $cajero->forceFill(['id'=>2,'nombreCompleto'=>'Cajero','estado'=>1]);$cajero->exists=true;$this->actingAs($cajero);
        $this->get('/admin/ventas/cobrar/'.$pedido->id)->assertOk()->assertSee('Corregir pedido')->assertDontSee('sin disponibilidad suficiente');
        $this->get('/admin/ventas/corregir-pedido/'.$pedido->id)->assertOk()->assertSee('stock 3');
        $this->get('/admin/pedidos/editar/'.$pedido->id)->assertForbidden();
        $this->put('/admin/ventas/corregir-pedido/'.$pedido->id,['detalles'=>[['producto_id'=>1,'cantidad'=>1],['producto_id'=>$otro->id,'cantidad'=>1]]])->assertSessionHasNoErrors()->assertRedirect('/admin/ventas/cobrar/'.$pedido->id);
        $this->assertEquals(23,$pedido->fresh()->total);$this->assertEquals(1,$pedido->fresh()->usuario_id);
        $this->post('/admin/ventas/cobrar/'.$pedido->id,['version_pedido'=>$versionAnterior,'metodo_pago'=>'Efectivo','monto_recibido'=>30])->assertSessionHasErrors('pedido');
        $this->assertEquals(0,Venta::count());
        $this->post('/admin/ventas/cobrar/'.$pedido->id,['version_pedido'=>$pedido->fresh()->versionCobro(),'metodo_pago'=>'Efectivo','monto_recibido'=>30])->assertSessionHasNoErrors();
        $this->assertEquals(23,Venta::first()->total);$this->assertEquals(7,Venta::first()->cambio);
        $this->assertEquals(2,Stock::where('producto_id',1)->first()->stock_actual);
        $this->assertEquals(9,Stock::where('producto_id',$otro->id)->first()->stock_actual);
        $this->put('/admin/ventas/corregir-pedido/'.$pedido->id,['detalles'=>[['producto_id'=>$otro->id,'cantidad'=>1]]])->assertSessionHasErrors('pedido');
        $this->assertTrue(Bitacora::where('tablaAfectada','pedidos')->where('accion','ACTUALIZAR')->where('idUsuario',2)->exists());
    }
    public function test_siguiente_pedido_abre_pendiente_y_omite_pagados_cancelados(): void
    {
        $primero=$this->nuevoPedido(1);$segundo=$this->nuevoPedido(1);$tercero=$this->nuevoPedido(1);
        $primero->update(['estado'=>'Cancelado']);
        $this->get('/admin/ventas/siguiente/'.$tercero->id)->assertRedirect('/admin/ventas/cobrar/'.$segundo->id);
        $this->post('/admin/ventas/cobrar/'.$segundo->id,['version_pedido'=>$segundo->versionCobro(),'metodo_pago'=>'QR'])->assertSessionHasNoErrors();
        $this->get('/admin/pedidos/ver/'.$segundo->id)->assertOk()->assertSee('Siguiente pedido');
        $this->get('/admin/ventas/siguiente/'.$segundo->id)->assertRedirect('/admin/ventas/cobrar/'.$tercero->id);
        $tercero->update(['estado'=>'Cancelado']);
        $this->get('/admin/ventas/siguiente/'.$segundo->id)->assertRedirect('/admin/ventas')->assertSessionHas('success','No hay más pedidos pendientes de cobro.');
    }
    public function test_hora_bolivia_muestra_utc_como_local_y_filtra_dia_correcto(): void
    {
        $this->assertEquals('America/La_Paz',config('app.timezone'));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 22:40:00','America/La_Paz'));
        try {
            $pedido=$this->nuevoPedido(1);
            $this->assertEquals('2026-10-07 02:40:00',DB::table('pedidos')->where('id',$pedido->id)->value('fechaHora'));
            $this->assertEquals('2026-10-06 22:40:00',$pedido->fresh()->fechaHora->format('Y-m-d H:i:s'));
            $this->get('/admin/pedidos?fecha=2026-10-06')->assertOk()->assertViewHas('pedidos',fn($p)=>$p->total()===1)->assertSee('2026-10-06 22:40:00');
            $this->get('/admin/pedidos?fecha=2026-10-07')->assertViewHas('pedidos',fn($p)=>$p->total()===0);
            $this->post('/admin/ventas/cobrar/'.$pedido->id,['version_pedido'=>$pedido->versionCobro(),'metodo_pago'=>'QR'])->assertSessionHasNoErrors();
            $this->get('/admin/reportes/ventas-diarias?fecha=2026-10-06')->assertViewHas('cantidad',1)->assertViewHas('total',12.35);
            $this->get('/admin/reportes/ventas-diarias?fecha=2026-10-07')->assertViewHas('cantidad',0);
            $this->get('/admin/reportes/metodos-pago?desde=2026-10-06&hasta=2026-10-06')->assertSee('12.35');
            $this->get('/admin/reportes/inventario?modo=movimientos&desde=2026-10-06&hasta=2026-10-06')->assertSee('Reserva del pedido');
        } finally { $this->travelBack(); }
    }
    public function test_mesero_y_cajero_consultan_y_aplican_promociones_vigentes(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 15:00:00','America/La_Paz'));
        try {
            $datos=$this->datosPromocion()+['horaInicio'=>'14:00','horaFin'=>'18:00'];
            $this->post('/admin/promociones/crear/store',$datos)->assertSessionHasNoErrors();$promo=Promocion::first();
            $mesero=new class extends User { public function tienePermiso(string $nombre): bool { return in_array($nombre,['registrar_pedidos','modificar_pedidos']); } };
            $mesero->forceFill(['id'=>1,'nombreCompleto'=>'Mesero','estado'=>1]);$mesero->exists=true;$this->actingAs($mesero);
            $this->get('/admin/promociones/disponibles')->assertOk()->assertSee('Activa y vigente')->assertDontSee('Nueva promoción');
            $this->get('/admin/promociones')->assertForbidden();
            $this->get('/admin/pedidos/nuevo')->assertOk()->assertSee($promo->nombre);
            $pedido=$this->nuevoPedido(2,$promo->id);$this->assertEquals(2.47,$pedido->descuento);
            $cajero=new class extends User { public function tienePermiso(string $nombre): bool { return $nombre==='cobrar_ventas'; } };
            $cajero->forceFill(['id'=>1,'nombreCompleto'=>'Cajero','estado'=>1]);$cajero->exists=true;$this->actingAs($cajero);
            $this->get('/admin/promociones/disponibles')->assertOk()->assertDontSee('Nueva promoción');
            $this->get('/admin/ventas/corregir-pedido/'.$pedido->id)->assertOk()->assertSee($promo->nombre);
            $this->put('/admin/ventas/corregir-pedido/'.$pedido->id,['detalles'=>[['producto_id'=>1,'cantidad'=>2]],'promocion_id'=>$promo->id])->assertSessionHasNoErrors();
            $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 23:00:00','America/La_Paz'));
            $this->assertFalse($promo->fresh()->vigente());
            $this->get('/admin/promociones/disponibles')->assertOk()->assertSee('Fuera de horario: 14:00 a 18:00 (Bolivia)');
            $this->put('/admin/ventas/corregir-pedido/'.$pedido->id,['detalles'=>[['producto_id'=>1,'cantidad'=>2]],'promocion_id'=>$promo->id])->assertSessionHasErrors('promocion_id');
        } finally { $this->travelBack(); }
    }
    public function test_reserva_impide_sobreventa_y_cancelar_devuelve_stock(): void
    {
        $pedido=$this->nuevoPedido(8);
        $this->assertEquals(2,Stock::first()->stock_actual);
        $this->post('/admin/pedidos/nuevo',['detalles'=>[['producto_id'=>1,'cantidad'=>3]]])->assertSessionHasErrors();
        $this->post('/admin/pedidos/cancelar/'.$pedido->id)->assertSessionHasNoErrors();
        $this->assertEquals(10,Stock::first()->stock_actual);
        $this->post('/admin/pedidos/cancelar/'.$pedido->id)->assertSessionHasErrors('pedido');
        $this->assertEquals(10,Stock::first()->stock_actual);
    }
    public function test_cantidades_enteras_y_edicion_fallida_conserva_reserva(): void
    {
        $this->movimiento('entrada',1.5)->assertSessionHasErrors('cantidad');
        $this->put('/admin/stocks/1/minimo',['stock_minimo'=>2.5])->assertSessionHasErrors('stock_minimo');
        $this->post('/admin/pedidos/nuevo',['detalles'=>[['producto_id'=>1,'cantidad'=>1.5]]])->assertSessionHasErrors('detalles.0.cantidad');
        $pedido=$this->nuevoPedido(3);
        $this->put('/admin/pedidos/editar/'.$pedido->id,['detalles'=>[['producto_id'=>1,'cantidad'=>11]]])->assertSessionHasErrors();
        $this->assertEquals(7,Stock::first()->stock_actual);
        $this->assertEquals(3,$pedido->fresh()->detalles->first()->cantidad);
    }
    public function test_numero_diario_reinicia_sin_reutilizar_id(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-11 23:59:00','America/La_Paz'));
        try {
            $primero=$this->nuevoPedido(1);$segundo=$this->nuevoPedido(1);
            $this->assertEquals(1,$primero->numero_diario);$this->assertEquals(2,$segundo->numero_diario);
            $this->travelTo(now()->addMinutes(2));$tercero=$this->nuevoPedido(1);
            $this->assertEquals(1,$tercero->numero_diario);$this->assertNotEquals($primero->id,$tercero->id);
        } finally {$this->travelBack();}
    }
    public function test_pago_combinado_valida_importes_y_reporte(): void
    {
        $pedido=$this->nuevoPedido(2);$version=$pedido->versionCobro();
        $datos=['version_pedido'=>$version,'metodo_pago'=>'Mixto','monto_qr'=>10,'monto_recibido'=>10];
        $this->post('/admin/ventas/cobrar/'.$pedido->id,$datos)->assertSessionHasErrors('monto_recibido');
        $this->assertEquals(0,Venta::count());
        $this->post('/admin/ventas/cobrar/'.$pedido->id,array_replace($datos,['monto_recibido'=>20]))->assertSessionHasNoErrors();
        $venta=Venta::first();$this->assertEquals(10,$venta->monto_qr);$this->assertEquals(14.70,$venta->monto_efectivo);$this->assertEquals(5.30,$venta->cambio);
        $this->assertEquals(8,Stock::first()->stock_actual);
        $this->get('/admin/reportes/metodos-pago')->assertOk()->assertSee('14.70')->assertSee('10.00');
    }
    public function test_promocion_varios_productos_descuenta_solo_seleccionados(): void
    {
        $otro=Producto::create(['nombre'=>'Té','precio'=>5,'disponible'=>true,'categoria_id'=>1]);Stock::create(['producto_id'=>$otro->id,'stock_actual'=>10,'stock_minimo'=>0]);
        $tercero=Producto::create(['nombre'=>'Pan','precio'=>3,'disponible'=>true,'categoria_id'=>1]);Stock::create(['producto_id'=>$tercero->id,'stock_actual'=>10,'stock_minimo'=>0]);
        $datos=array_replace($this->datosPromocion(),['aplicaSobre'=>'Producto','producto_ids'=>[1,$otro->id],'valor'=>50]);
        $this->post('/admin/promociones/crear/store',$datos)->assertSessionHasNoErrors();
        $promo=Promocion::first();$this->assertEquals([1,$otro->id],$promo->productosAplicables());
        $this->post('/admin/pedidos/nuevo',['detalles'=>[['producto_id'=>1,'cantidad'=>1],['producto_id'=>$otro->id,'cantidad'=>1],['producto_id'=>$tercero->id,'cantidad'=>1]],'promocion_id'=>$promo->id])->assertSessionHasNoErrors();
        $this->assertEquals(8.68,Pedido::first()->descuento);
        $this->get('/admin/promociones')->assertOk()->assertSee('Té')->assertSee('12.35');
    }
    public function test_segundo_acceso_no_desplaza_al_primero_y_logout_libera_la_cuenta(): void
    {
        $user=User::first();
        $user->forceFill(['nombreUsuario'=>'admin-prueba','password'=>\Illuminate\Support\Facades\Hash::make('ClavePrueba123'),'session_token'=>'primero','session_last_activity'=>now()->timestamp])->save();
        auth()->logout();
        $this->post('/login',['nombreUsuario'=>'admin-prueba','password'=>'ClavePrueba123'])->assertSessionHasErrors('nombreUsuario');
        $this->assertGuest();
        $this->assertEquals('primero',$user->fresh()->session_token);
        $this->actingAs($user)->withSession(['cuenta_token'=>'primero'])->post('/logout')->assertRedirect('/login');
        $this->assertNull($user->fresh()->session_token);
        $this->post('/login',['nombreUsuario'=>'admin-prueba','password'=>'ClavePrueba123'])->assertSessionHasNoErrors()->assertRedirect('/home');
        $this->assertAuthenticated();
    }
    public function test_sesion_vencida_permite_un_nuevo_acceso(): void
    {
        $user=User::first();
        $user->forceFill(['nombreUsuario'=>'admin-prueba','password'=>\Illuminate\Support\Facades\Hash::make('ClavePrueba123'),'session_token'=>'vencido','session_last_activity'=>now()->subMinutes((int)config('session.lifetime')+1)->timestamp])->save();
        auth()->logout();
        $this->post('/login',['nombreUsuario'=>'admin-prueba','password'=>'ClavePrueba123'])->assertSessionHasNoErrors()->assertRedirect('/home');
        $this->assertNotEquals('vencido',$user->fresh()->session_token);
    }
    public function test_sesion_anterior_se_invalida_y_cuenta_eliminada_conserva_historial(): void
    {
        $pedido=$this->nuevoPedido(1);
        $user=User::first();$user->forceFill(['session_token'=>'nuevo'])->save();
        $this->actingAs($user)->withSession(['cuenta_token'=>'anterior'])->get('/home')->assertRedirect('/login');
        $this->actingAs($user)->withSession(['cuenta_token'=>'nuevo'])->get('/home')->assertOk();
        $user->delete();
        $this->assertNull(User::find(1));$this->assertNotNull($pedido->fresh()->usuario);
        $this->actingAs($user)->get('/home')->assertRedirect('/login');
    }
    public function test_recuperacion_se_envia_solo_al_administrador(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $user=User::first();$user->forceFill(['email'=>'empleado@example.com','nombreUsuario'=>'empleado']);
        $user->sendPasswordResetNotification('codigo-prueba');
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(\App\Notifications\RecuperacionAdministrador::class,function($n,$canales,$destino){
            return $destino->routes['mail']==='Proyectocafeteriakarma@gmail.com' && $n->email==='empleado@example.com';
        });
    }
    public function test_pantallas_renderizan_y_permisos_se_aplican(): void
    {
        foreach(['/admin/stocks','/admin/stock-minimo','/admin/agotados','/admin/catalogo?q=Café','/admin/inventario/ajuste','/admin/pedidos/nuevo','/admin/movimientos'] as $url) $this->get($url)->assertOk();
        $denegado=new class extends User { public function tienePermiso(string $nombre): bool { return false; } };
        $denegado->forceFill(['id'=>1,'estado'=>1]);$this->actingAs($denegado);
        $this->get('/admin/inventario/entrada')->assertForbidden();
        $this->post('/admin/pedidos/nuevo',['detalles'=>[['producto_id'=>1,'cantidad'=>1]]])->assertForbidden();
    }
}






