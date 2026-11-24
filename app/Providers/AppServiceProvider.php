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
        $this->app->singleton(\App\Services\Caja\AislamientoCajaDemo::class);
        if (\App\Services\Caja\AislamientoCajaDemo::solicitada()) {
            $this->app->bind(\App\Services\Caja\CoordinacionCobrosCaja::class,
                \App\Services\Caja\CoordinacionCajaDemostracion::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (\App\Services\Caja\AislamientoCajaDemo::solicitada()) {
            // Antes de controladores, sesiones y cualquier preparador de datos.
            app(\App\Services\Caja\AislamientoCajaDemo::class)->vigilar();
        }
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
