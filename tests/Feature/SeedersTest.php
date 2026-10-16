<?php

namespace Tests\Feature;

use App\Models\{Rol, Permiso, MetodoPago};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    public function test_carga_basica_repetible_conserva_la_configuracion_existente(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        foreach ([
            '2014_10_11_201944_create_roles_table.php',
            '2026_09_04_205700_create_permisos_table.php',
            '2026_09_04_205720_create_metodo_pagos_table.php',
            '2026_09_04_210100_create_rol_permisos_table.php',
        ] as $archivo) {
            (require database_path('migrations/'.$archivo))->up();
        }

        $this->seed(DatabaseSeeder::class);
        $this->assertSame(3, Rol::count());
        $this->assertSame(20, Permiso::count());
        $this->assertSame(['Efectivo', 'QR', 'Mixto'], MetodoPago::pluck('nombre')->all());
        $admin = Rol::where('nombre', 'Administrador')->firstOrFail();
        $this->assertTrue($admin->permisos()->where('nombre', 'gestion_usuarios')->exists());
        $admin->update(['estado' => false]);
        $admin->permisos()->detach();
        Permiso::where('nombre', 'gestion_usuarios')->update(['descripcion' => 'Personalizado']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Rol::count());
        $this->assertSame(20, Permiso::count());
        $this->assertSame(3, MetodoPago::count());
        $this->assertFalse($admin->fresh()->estado);
        $this->assertSame(0, $admin->permisos()->count());
        $this->assertSame('Personalizado', Permiso::where('nombre', 'gestion_usuarios')->value('descripcion'));
    }
}
