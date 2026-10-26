<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\{Bitacora, CierreCajaVersion, CierreCajaVersionVenta, User};
use App\Services\Caja\{CierreDiarioService, RectificacionCierreDiarioService, ReconciliacionCajaService,
    CoordinacionCobrosCaja, CoordinadorCobrosCaja, FalloCoordinacionCaja};
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Validation\ValidationException;
use LogicException;
use PDOException;
use RuntimeException;
use Tests\Support\PdoCommitCajaSimulado;
use Tests\TestCase;

class CajaRecuperacionTest extends TestCase
{
    private PdoCommitCajaSimulado $pdo;
    private int $cierre;
    private int $permisoRectificar;
    private array $baseOperativa;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) throw new LogicException('Solo SQLite en memoria.');
        $this->pdo = new PdoCommitCajaSimulado;
        DB::connection()->setPdo($this->pdo);
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach (DB::select('PRAGMA database_list') as $base) $this->assertSame('', $base->file);
        foreach (glob(database_path('migrations/*.php')) as $archivo) (require $archivo)->up();
        DB::table('roles')->insert(['rol_id'=>1,'nombre'=>'Supervisor sintético','estado'=>1]);
        DB::table('usuarios')->insert(['id'=>1,'rol_id'=>1,'nombreCompleto'=>'Actor sintético','nombreUsuario'=>'recuperacion-caja','password'=>'CLAVE_NO_EXPORTAR','estado'=>1]);
        foreach (['consultar_caja','confirmar_cierre_caja','rectificar_cierre_caja','consultar_bitacora'] as $nombre) {
            $id=DB::table('permisos')->insertGetId(['nombre'=>$nombre]);
            DB::table('rol_permisos')->insert(['idRol'=>1,'idPermiso'=>$id]);
            if ($nombre === 'rectificar_cierre_caja') $this->permisoRectificar=$id;
        }
        DB::table('categorias')->insert(['id'=>1,'nombre'=>'Sintética','estado'=>1]);
        DB::table('productos')->insert(['id'=>1,'nombre'=>'Producto sintético','categoria_id'=>1,'precio'=>'10.10','disponible'=>1]);
        DB::table('stocks')->insert(['producto_id'=>1,'stock_actual'=>3,'stock_minimo'=>1]);
        foreach (['2026-10-05','2026-10-06'] as $fecha) {
            $pedido=DB::table('pedidos')->insertGetId(['usuario_id'=>1,'fechaHora'=>$fecha.' 16:00:00','total'=>'10.10','estado'=>'Pagado']);
            DB::table('detalle_pedidos')->insert(['pedido_id'=>$pedido,'producto_id'=>1,'cantidad'=>1,'opcion'=>'Normal','precioUnitario'=>'10.10','subtotal'=>'10.10']);
            DB::table('ventas')->insert(['pedido_id'=>$pedido,'usuario_id'=>1,'fecha_hora'=>$fecha.' 16:00:00','total'=>'10.10',
                'monto_efectivo'=>'10.10','monto_qr'=>'0.00','monto_recibido'=>'20.00','cambio'=>'9.90','metodo_pago'=>'Efectivo','estado'=>'Completado']);
        }
        $this->actingAs(User::findOrFail(1));
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-07 12:00:00','America/La_Paz'));
        $base=(new CierreDiarioService($this->coordinador()))->confirmar('2026-10-05','10.10');
        $this->cierre=$base->cierre_caja_id;
        $this->baseOperativa=$this->operativa();
        $this->pdo->intentosCommit=0;
    }

    protected function tearDown(): void { $this->pdo->fallo='ninguno'; $this->travelBack(); parent::tearDown(); }

    private function coordinador(?Closure $antes=null, ?Closure $despues=null): CoordinacionCobrosCaja
    {
        return new class($antes,$despues) implements CoordinacionCobrosCaja {
            public function __construct(private ?Closure $antes, private ?Closure $despues) {}
            public function permiteCierreDefinitivo(): bool { return true; }
            public function exclusivo(Closure $operacion): mixed
            {
                if (!app()->runningUnitTests() || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') throw new LogicException('Solo pruebas en memoria.');
                ($this->antes ?? static fn()=>null)();
                $resultado=(new CoordinadorCobrosCaja)->exclusivo($operacion);
                ($this->despues ?? static fn()=>null)();
                return $resultado;
            }
        };
    }

    private function operativa(): array
    {
        $datos=[];
        foreach (['ventas','pedidos','detalle_pedidos','stocks','productos','movimiento_inventarios'] as $tabla) $datos[$tabla]=DB::table($tabla)->orderBy('id')->get()->toJson();
        return $datos;
    }

    private function rectificar(?Closure $antes=null, ?Closure $despues=null)
    {
        return (new RectificacionCierreDiarioService($this->coordinador($antes,$despues)))->rectificar($this->cierre,1,'9.09','Recuento supervisado','Nota protegida');
    }

    private function incidente(): array
    {
        $evento=Bitacora::where('accion','like','RECTIFICACION_CAJA_%')->orderByDesc('id')->firstOrFail();
        return json_decode($evento->valoresNuevos,true,flags:JSON_THROW_ON_ERROR);
    }

    public function test_error_antes_de_commit_revierte_version_instantaneas_y_confirma_rollback(): void
    {
        CierreCajaVersionVenta::creating(static fn()=>throw new RuntimeException('password=NO_EXPORTAR SQLSTATE'));
        try { $this->rectificar(); $this->fail('Debe fallar antes de commit.'); } catch (RuntimeException) {}
        $this->assertSame('ROLLBACK_CONFIRMADO',$this->incidente()['persistencia']);
        $this->assertSame('FALLIDA',$this->incidente()['resultado']);
        $this->assertSame(1,CierreCajaVersion::count());
        $this->assertSame(1,CierreCajaVersionVenta::count());
        $this->assertSame(0,Bitacora::where('accion','RECTIFICACION_CAJA')->count());
        $this->assertSame($this->baseOperativa,$this->operativa());
        $lectura=(new ReconciliacionCajaService)->consultar('2026-10-05',1);
        $this->assertSame('VERSION_OBJETIVO_NO_OBSERVADA',$lectura['estado']);
        $this->assertFalse($lectura['reintento_automatico_permitido']);
    }

    public function test_error_durante_commit_sin_aplicarlo_no_se_confunde_con_rollback_certificado(): void
    {
        $this->pdo->fallo='antes';
        try { $this->rectificar(); $this->fail('Respuesta perdida sintética.'); } catch (PDOException) {}
        $this->assertSame(1,$this->pdo->intentosCommit);
        $this->assertSame('INCIERTO',$this->incidente()['resultado']);
        $this->assertSame('INCIERTA',$this->incidente()['persistencia']);
        $this->assertSame(1,CierreCajaVersion::count());
        $this->assertSame('VERSION_OBJETIVO_NO_OBSERVADA',(new ReconciliacionCajaService)->consultar('2026-10-05',1)['estado']);
        $this->assertSame($this->baseOperativa,$this->operativa());
        $this->assertSame(0,DB::transactionLevel());
    }

    public function test_respuesta_perdida_con_commit_aplicado_se_reconcilia_sin_duplicar_version(): void
    {
        $this->pdo->fallo='despues';
        try { $this->rectificar(); $this->fail('Commit aplicado pero sin acuse.'); } catch (PDOException) {}
        $this->assertSame(1,$this->pdo->intentosCommit);
        $this->assertSame('INCIERTO',$this->incidente()['resultado']);
        $this->assertSame('INCIERTA',$this->incidente()['persistencia']);
        $lectura=(new ReconciliacionCajaService)->consultar('2026-10-05',1,$this->incidente()['operacion_id']);
        $this->assertSame('CONFIRMACION_OBSERVADA',$lectura['estado']);
        $this->assertSame('COINCIDE',$lectura['atribucion_solicitud']);
        $this->assertSame(2,$lectura['version_vigente_observada']);
        $this->assertSame(1,$lectura['versiones'][1]['eventos_confirmados']);
        $this->assertSame(2,CierreCajaVersionVenta::count());
        $this->assertSame($this->baseOperativa,$this->operativa());
        $this->assertSame(1,$this->pdo->intentosCommit);
    }

    public function test_confirmacion_diaria_con_commit_aplicado_y_acuse_perdido_no_se_repite(): void
    {
        $this->pdo->fallo='despues';
        try { (new CierreDiarioService($this->coordinador()))->confirmar('2026-10-06','10.10'); $this->fail('Acuse perdido.'); } catch (PDOException) {}
        $lectura=(new ReconciliacionCajaService)->consultar('2026-10-06');
        $this->assertSame('CONFIRMACION_OBSERVADA',$lectura['estado']);
        $this->assertSame('NO_VERIFICADA',$lectura['atribucion_solicitud']);
        $this->assertSame(1,$this->pdo->intentosCommit);
        $this->assertSame(2,Bitacora::where('accion','CIERRE_CAJA')->count());
        $this->assertSame($this->baseOperativa,$this->operativa());
        $this->pdo->fallo='ninguno';
        try { (new CierreDiarioService($this->coordinador()))->confirmar('2026-10-06','10.10'); $this->fail('Reenvío manual duplicado.'); }
        catch (ValidationException) {}
        $this->assertSame(2,DB::table('cierre_cajas')->count());
        $this->assertSame(2,Bitacora::where('accion','CIERRE_CAJA')->count());
        $this->assertSame(1,$this->pdo->intentosCommit);
    }

    public function test_fallo_despues_del_commit_y_liberacion_conserva_confirmado_e_incidente_distintos(): void
    {
        try { $this->rectificar(null,static fn()=>throw new FalloCoordinacionCaja('token=NO_EXPORTAR',true)); $this->fail('Resultado incierto.'); }
        catch (FalloCoordinacionCaja) {}
        $incidente=$this->incidente();
        $this->assertSame('COMMIT_CONFIRMADO',$incidente['persistencia']);
        $confirmado=json_decode(Bitacora::where('accion','RECTIFICACION_CAJA')->sole()->valoresNuevos,true,flags:JSON_THROW_ON_ERROR);
        $this->assertSame($confirmado['operacion_id'],$incidente['operacion_id']);
        $this->assertNotSame($confirmado['evento_id'],$incidente['evento_id']);
        $this->assertSame('CONFIRMACION_OBSERVADA',(new ReconciliacionCajaService)->consultar('2026-10-05',1,$incidente['operacion_id'])['estado']);
    }

    public function test_reconciliar_repetidamente_no_modifica_finanzas_ni_genera_eventos(): void
    {
        $this->rectificar();
        $tablas=['cierre_cajas','cierre_caja_versiones','cierre_caja_version_ventas','bitacoras']; $antes=[];
        foreach ($tablas as $tabla) $antes[$tabla]=DB::table($tabla)->orderBy('id')->get()->toJson();
        DB::enableQueryLog(); DB::flushQueryLog();
        foreach (range(1,3) as $veces) $this->assertSame('CONFIRMACION_OBSERVADA',(new ReconciliacionCajaService)->consultar('2026-10-05',1)['estado']);
        foreach (DB::getQueryLog() as $q) {
            $this->assertDoesNotMatchRegularExpression('/\A\s*(insert|update|delete|begin|commit)\b/i',$q['query']);
            $this->assertStringNotContainsString('GET_LOCK',$q['query']);
        }
        foreach ($antes as $tabla=>$json) $this->assertSame($json,DB::table($tabla)->orderBy('id')->get()->toJson());
        $this->assertSame($this->baseOperativa,$this->operativa());
    }

    public function test_evento_confirmado_duplicado_o_ausente_exige_revision_no_reparacion(): void
    {
        $this->rectificar();
        $evento=DB::table('bitacoras')->where('accion','RECTIFICACION_CAJA')->first();
        $copia=(array)$evento; unset($copia['id']); DB::table('bitacoras')->insert($copia);
        $resultado=(new ReconciliacionCajaService)->consultar('2026-10-05',1);
        $this->assertSame('REQUIERE_REVISION',$resultado['estado']);
        $this->assertSame(2,$resultado['versiones'][1]['eventos_confirmados']);
        DB::table('bitacoras')->where('accion','RECTIFICACION_CAJA')->delete(); // Solo fixture en memoria.
        $this->assertSame('REQUIERE_REVISION',(new ReconciliacionCajaService)->consultar('2026-10-05',1)['estado']);
    }

    public function test_uuid_distinto_no_atribuye_otra_rectificacion_al_intento(): void
    {
        $this->rectificar();
        $resultado=(new ReconciliacionCajaService)->consultar('2026-10-05',1,'00000000-0000-4000-8000-000000000001');
        $this->assertSame('REQUIERE_REVISION',$resultado['estado']);
        $this->assertSame('NO_COINCIDE',$resultado['atribucion_solicitud']);
    }

    public function test_sin_caja_o_bitacora_o_actor_valido_no_se_lee_informacion_financiera(): void
    {
        foreach (['consultar_bitacora','consultar_caja'] as $nombre) {
            $id=DB::table('permisos')->where('nombre',$nombre)->value('id');
            DB::table('rol_permisos')->where('idPermiso',$id)->delete();
            DB::enableQueryLog(); DB::flushQueryLog();
            try { (new ReconciliacionCajaService)->consultar('2026-10-05'); $this->fail('Debe denegar.'); } catch (AuthorizationException) {}
            $sql=implode("\n",array_column(DB::getQueryLog(),'query'));
            $this->assertStringNotContainsString('cierre_cajas',$sql); $this->assertStringNotContainsString('bitacoras',$sql);
            DB::table('rol_permisos')->insert(['idRol'=>1,'idPermiso'=>$id]);
        }
        Auth::logout();
        $this->expectException(AuthenticationException::class);
        (new ReconciliacionCajaService)->consultar('2026-10-05');
    }

    public function test_revocacion_mientras_espera_y_reconciliacion_no_repite_la_operacion(): void
    {
        try { $this->rectificar(fn()=>DB::table('rol_permisos')->where('idPermiso',$this->permisoRectificar)->delete()); $this->fail('Permiso revocado.'); }
        catch (AuthorizationException) {}
        $this->assertSame(1,CierreCajaVersion::count());
        $this->assertSame('ROLLBACK_CONFIRMADO',$this->incidente()['persistencia']);
        $this->assertSame('VERSION_OBJETIVO_NO_OBSERVADA',(new ReconciliacionCajaService)->consultar('2026-10-05',1)['estado']);
    }

    public function test_no_se_exponen_observaciones_motivos_sql_credenciales_o_usuarios_completos(): void
    {
        $this->rectificar();
        $json=json_encode((new ReconciliacionCajaService)->consultar('2026-10-05',1),JSON_THROW_ON_ERROR);
        foreach (['Nota protegida','Recuento supervisado','CLAVE_NO_EXPORTAR','password','token','SQLSTATE','nombreCompleto','nombreUsuario','"ip"'] as $secreto) $this->assertStringNotContainsString($secreto,$json);
        $this->assertFalse((new CoordinadorCobrosCaja)->permiteCierreDefinitivo());
    }

    public function test_validacion_y_ausencia_no_autorizan_reintento_ni_afirmacion_de_rollback(): void
    {
        $servicio=new ReconciliacionCajaService;
        $resultado=$servicio->consultar('2026-10-04');
        $this->assertSame('SIN_CIERRE_OBSERVADO',$resultado['estado']);
        $this->assertFalse($resultado['reintento_automatico_permitido']);
        foreach ([['2026-02-30',null,null],['2026-10-05',0,null],['2026-10-05',1,'password=SECRETO']] as $datos) {
            try { $servicio->consultar(...$datos); $this->fail('Entrada inválida.'); } catch (ValidationException) { $this->assertTrue(true); }
        }
    }

    public function test_evento_de_incidente_se_intenta_una_sola_vez_por_operacion(): void
    {
        $op=new \App\Services\Caja\OperacionRectificacionCaja($this->cierre,1);
        $op->actorId=1; $op->cierreVerificado=true; $op->fecha='2026-10-05';
        $op->iniciada=true; $op->rollbackConfirmado=true;
        $auditoria=new \App\Services\Caja\AuditoriaFinancieraCaja;
        $error=new RuntimeException('password=NO_EXPORTAR SQLSTATE');
        $auditoria->incidente($op,$error); $auditoria->incidente($op,$error);
        $this->assertSame(1,Bitacora::where('accion','RECTIFICACION_CAJA_FALLIDA')->count());
        $this->assertStringNotContainsString('NO_EXPORTAR',Bitacora::where('accion','RECTIFICACION_CAJA_FALLIDA')->sole()->valoresNuevos);
    }

    public function test_reenvio_manual_con_version_obsoleta_no_duplica_version_confirmada(): void
    {
        $this->rectificar();
        try { $this->rectificar(); $this->fail('Versión obsoleta.'); } catch (ValidationException) {}
        $this->assertSame(2,CierreCajaVersion::count());
        $this->assertSame(2,CierreCajaVersionVenta::count());
        $this->assertSame(1,Bitacora::where('accion','RECTIFICACION_CAJA')->count());
        $this->assertSame($this->baseOperativa,$this->operativa());
    }

    public function test_instantanea_o_auditoria_incoherente_obliga_a_revision_sin_reparar(): void
    {
        DB::table('cierre_caja_version_ventas')->update(['monto_qr'=>'1.00']); // Solo fixture.
        $this->assertSame('REQUIERE_REVISION',(new ReconciliacionCajaService)->consultar('2026-10-05')['estado']);
        DB::table('cierre_caja_version_ventas')->update(['monto_qr'=>'0.00']);
        $evento=Bitacora::where('accion','CIERRE_CAJA')->sole();
        $meta=json_decode($evento->valoresNuevos,true,flags:JSON_THROW_ON_ERROR); $meta['efectivo_contado']='999.99';
        DB::table('bitacoras')->where('id',$evento->id)->update(['valoresNuevos'=>json_encode($meta,JSON_THROW_ON_ERROR)]);
        $this->assertSame('REQUIERE_REVISION',(new ReconciliacionCajaService)->consultar('2026-10-05')['estado']);
    }

    public function test_reconciliacion_rechaza_transaccion_pdo_ajena_y_no_confirma_historicos_null(): void
    {
        $this->pdo->beginTransaction();
        try { (new ReconciliacionCajaService)->consultar('2026-10-05'); $this->fail('No leer datos propios no confirmados.'); }
        catch (LogicException) { $this->assertTrue($this->pdo->inTransaction()); }
        finally { $this->pdo->rollBack(); }
        DB::table('cierre_cajas')->where('id',$this->cierre)->update(['fecha_operativa'=>null]);
        $this->assertSame('SIN_CIERRE_OBSERVADO',(new ReconciliacionCajaService)->consultar('2026-10-05')['estado']);
    }
}
