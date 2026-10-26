<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cierre_cajas', function (Blueprint $table) {
            // NULL conserva los registros históricos sin declararlos oficiales.
            $table->date('fecha_operativa')->nullable()->unique('cierre_cajas_fecha_operativa_unique');
        });
        $this->cambiarRelacionResponsable(false);

        Schema::create('cierre_caja_versiones', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('cierre_caja_id')->constrained('cierre_cajas')->restrictOnDelete();
            $table->unsignedInteger('numero_version');
            $table->foreignId('responsable_id')->constrained('usuarios')->restrictOnDelete();
            $table->dateTime('registrado_en'); // UTC; se presenta en America/La_Paz.
            $table->decimal('total_ventas', 16, 2);
            $table->decimal('efectivo_esperado', 16, 2);
            $table->decimal('qr_registrado', 16, 2);
            $table->decimal('efectivo_contado', 16, 2);
            // La base calcula el resultado; nunca se recibe como importe editable.
            $table->decimal('diferencia', 17, 2)->storedAs('efectivo_contado - efectivo_esperado');
            $table->text('observacion')->nullable();
            $table->text('motivo_correccion')->nullable();
            $table->timestamps();
            $table->unique(['cierre_caja_id', 'numero_version'], 'caja_version_numero_unique');
            $table->index(['responsable_id', 'registrado_en'], 'caja_version_responsable_fecha_index');
        });

        Schema::create('cierre_caja_version_ventas', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('cierre_caja_version_id')->constrained('cierre_caja_versiones')->restrictOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            // Instantánea del cobro: no depende de cambios futuros en la venta.
            $table->dateTime('fecha_cobro');
            $table->string('metodo_pago');
            $table->decimal('total', 16, 2);
            $table->decimal('monto_efectivo', 16, 2);
            $table->decimal('monto_qr', 16, 2);
            $table->timestamps();
            $table->unique(['cierre_caja_version_id', 'venta_id'], 'caja_version_venta_unique');
            $table->index('venta_id', 'caja_detalle_venta_index');
        });
    }

    public function down(): void
    {
        // El DDL de MariaDB no se revierte con una transacción. Validar antes del primer DROP.
        foreach (['cierre_caja_version_ventas', 'cierre_caja_versiones'] as $tabla) {
            if (Schema::hasTable($tabla) && Schema::getConnection()->table($tabla)->exists()) {
                throw new RuntimeException('No se puede revertir caja: existen registros financieros versionados.');
            }
        }
        if (Schema::getConnection()->table('cierre_cajas')->whereNotNull('fecha_operativa')->exists()) {
            throw new RuntimeException('No se puede revertir caja: existen fechas operativas oficiales.');
        }

        Schema::dropIfExists('cierre_caja_version_ventas');
        Schema::dropIfExists('cierre_caja_versiones');
        $this->cambiarRelacionResponsable(true);
        Schema::table('cierre_cajas', function (Blueprint $table) {
            $table->dropUnique('cierre_cajas_fecha_operativa_unique');
            $table->dropColumn('fecha_operativa');
        });
    }

    private function cambiarRelacionResponsable(bool $restaurarCascade): void
    {
        $conexion = Schema::getConnection();
        if (in_array($conexion->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Un solo ALTER evita un intervalo sin FK. Los nombres son los del esquema histórico.
            $gramatica = $conexion->getSchemaGrammar();
            $tabla = $gramatica->wrapTable('cierre_cajas');
            $usuarios = $gramatica->wrapTable('usuarios');
            $anterior = $gramatica->wrap($restaurarCascade
                ? 'cierre_cajas_responsable_restrict_foreign' : 'cierre_cajas_idusuario_foreign');
            $nueva = $gramatica->wrap($restaurarCascade
                ? 'cierre_cajas_idusuario_foreign' : 'cierre_cajas_responsable_restrict_foreign');
            $accion = $restaurarCascade ? 'cascade' : 'restrict';
            $conexion->statement("alter table {$tabla} drop foreign key {$anterior}, "
                ."add constraint {$nueva} foreign key (`idUsuario`) references {$usuarios} (`id`) on delete {$accion}");
            return;
        }

        // SQLite reconstruye la tabla conservando sus filas, índices y relaciones.
        Schema::table('cierre_cajas', function (Blueprint $table) use ($restaurarCascade) {
            $table->dropForeign(['idUsuario']);
            $relacion = $table->foreign('idUsuario')->references('id')->on('usuarios');
            $relacion->onDelete($restaurarCascade ? 'cascade' : 'restrict');
        });
    }
};
