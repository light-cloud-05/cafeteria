<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolSeeder::class,
            UserSeeder::class,
            CategoriaSeeder::class,
            ProveedorSeeder::class,
            PermisoSeeder::class,
            MetodoPagosSeeder::class,
            PromocionesSeeder::class,
            RolPermisosSeeder::class,
            ProductoSeeder::class,
            StocksSeeder::class,
            MovimientoInventarioSeeder::class,
            CierreCajasSeeder::class,
            PedidoSeeder::class,
            DetallePedidoSeeder::class,
            VentaSeeder::class,
            BitacorasSeeder::class
        ]);
    }
}