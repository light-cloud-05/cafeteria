<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([\App\Models\User::class, \App\Models\Rol::class,
            \App\Models\Categoria::class, \App\Models\Producto::class,
            \App\Models\Proveedor::class, \App\Models\Promocion::class,
            \App\Models\Pedido::class, \App\Models\DetallePedido::class,
            \App\Models\Venta::class, \App\Models\MovimientoInventario::class,
            \App\Models\Stock::class] as $modelo) {
            $modelo::observe(\App\Observers\RegistrarCambioCritico::class);
        }
    }
}
