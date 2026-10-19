<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\RolPermisosController;
use App\Http\Controllers\MiContrasenaController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\StocksController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\PromocionesController;
use App\Http\Controllers\MetodoPagosController;
use App\Http\Controllers\BitacorasController;

/*
|--------------------------------------------------------------------------
| INICIO Y CIERRE DE SESIÓN
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'index'])
        ->name('login');

Route::post('/login', [SessionController::class, 'store'])
    ->name('login.store');
    Route::get('/recuperar-contrasena', [PasswordController::class, 'index'])
    ->name('password.request');

Route::post('/recuperar-contrasena', [PasswordController::class, 'store'])
    ->middleware('throttle:3,1')
    ->name('password.email');

Route::get('/restablecer-contrasena/{token}', [PasswordController::class, 'edit'])
    ->name('password.reset');

Route::post('/restablecer-contrasena', [PasswordController::class, 'update'])
    ->middleware('throttle:5,1')
    ->name('password.update');    
});

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('home');
    });

    Route::get('/home', [\App\Http\Controllers\InicioController::class, 'index'])->name('home');

    Route::post('/logout', [SessionController::class, 'destroy'])
        ->name('logout');

    Route::get('/prueba-permisos', function () {
        return 'Acceso autorizado: tienes el permiso gestion_usuarios.';
    })->middleware('permiso:gestion_usuarios');
});

/*
|--------------------------------------------------------------------------
| MÓDULOS PROTEGIDOS
|--------------------------------------------------------------------------
| El prefijo /admin es parte de la dirección.
| El permiso determina quién puede acceder.
*/

Route::middleware('auth')->prefix('admin')->group(function () {

    // USUARIOS
    Route::controller(UserController::class)
        ->middleware('permiso:gestion_usuarios')
        ->group(function () {
            Route::get('/usuarios', 'index')
                ->name('admin.indexusuario');

            Route::get('/usuarios/crear', 'create')
                ->name('admin.crearusuario');

            Route::post('/usuarios/crear/store', 'store')
                ->name('admin.storedusuario');

            Route::get('/usuarios/editar/{usuario}', 'edit')
                ->name('admin.editusuario');

            Route::post('/usuarios/editar/{usuario}', 'update')
                ->name('admin.updateusuario');

            Route::delete('/usuarios/eliminar/{usuario}', 'destroy')
                ->name('admin.destroyusuario');
        });

// ROLES Y PERMISOS
Route::middleware('permiso:gestion_roles')->group(function () {
    Route::get('/roles', [RolController::class, 'index'])
        ->name('admin.indexrol');

    Route::get('/roles/editar/{rol}', [RolController::class, 'edit'])
        ->name('admin.editrol');

    Route::post('/roles/editar/{rol}', [RolController::class, 'update'])
        ->name('admin.updaterol');

    Route::get('/permisos', function () {
        return redirect()->route('admin.indexrol');
    })->name('admin.indexpermiso');

    Route::get('/rol-permisos', function () {
        return redirect()->route('admin.indexrol');
    })->name('admin.indexrolpermiso');
});

    // PRODUCTOS
    Route::controller(ProductoController::class)
        ->middleware('permiso:gestion_productos')
        ->group(function () {
            Route::get('/productos', 'index')
                ->name('admin.indexproducto');

            Route::get('/productos/crear', 'create')
                ->name('admin.crearproducto');

            Route::post('/productos/crear/store', 'store')
                ->name('admin.storedproducto');

            Route::get('/productos/editar/{id}', 'edit')
                ->name('admin.editproducto');

            Route::post('/productos/editar/{id}', 'update')
                ->name('admin.updateproducto');

            Route::delete('/productos/eliminar/{id}', 'destroy')
                ->name('admin.destroyproducto');
        });

    // CATEGORÍAS
    Route::controller(CategoriaController::class)
        ->middleware('permiso:gestion_categorias')
        ->group(function () {
            Route::get('/categorias', 'index')
                ->name('admin.indexcategoria');

            Route::get('/categorias/crear', 'create')
                ->name('admin.crearcategoria');

            Route::post('/categorias/crear/store', 'store')
                ->name('admin.storedcategoria');

            Route::get('/categorias/editar/{id}', 'edit')
                ->name('admin.editarcategoria');

            Route::post('/categorias/editar/{id}', 'update')
                ->name('admin.updatecategoria');

            Route::delete('/categorias/eliminar/{id}', 'destroy')
                ->name('admin.destroycategoria');
        });

    // PROVEEDORES
    Route::controller(ProveedorController::class)
        ->middleware('permiso:gestion_proveedores')
        ->group(function () {
            Route::get('/proveedores', 'index')
                ->name('admin.indexproveedor');

            Route::get('/proveedores/crear', 'create')
                ->name('admin.crearproveedor');

            Route::post('/proveedores/crear/store', 'store')
                ->name('admin.storedproveedor');

            Route::get('/proveedores/editar/{id}', 'edit')
                ->name('admin.editproveedor');

            Route::post('/proveedores/editar/{id}', 'update')
                ->name('admin.updateproveedor');

            Route::delete('/proveedores/eliminar/{id}', 'destroy')
                ->name('admin.destroyproveedor');
        });

    // CONSULTA DE STOCK
    Route::get('/stocks', [StocksController::class, 'index'])
        ->middleware('permiso:consultar_stock')
        ->name('admin.indexstock');

    // La edición de stock se registra mediante movimientos
    // de ajuste, para conservar el historial.

    // HISTORIAL DE MOVIMIENTOS
    Route::get('/movimientos', [MovimientoInventarioController::class, 'index'])
        ->middleware('permiso:ver_reportes')
        ->name('admin.indexmovimiento');

    // El registro de entradas, salidas y ajustes está conectado más abajo.
    // El controlador comprueba el permiso según el tipo.

    // HISTORIAL DE PEDIDOS
    Route::get('/pedidos', [PedidoController::class, 'index'])
        ->middleware('permiso:consultar_historial_pedidos')
        ->name('admin.indexpedido');

    // Registro, modificación, cancelación y cambios de estado conectados más abajo.
    // Cada operación comprueba su permiso correspondiente.
    // La cancelación conserva el historial del pedido.

    // CONSULTA DE VENTAS PARA EL CAJERO
    Route::get('/ventas', [VentaController::class, 'index'])
        ->middleware('permiso:cobrar_ventas')
        ->name('admin.indexventa');

    // El cobro está conectado más abajo; calcula
    // el total en el servidor y evita cobros duplicados.

    // MÉTODOS DE PAGO
    Route::get('/metodos-pago', [MetodoPagosController::class, 'index'])
        ->middleware('permiso:cobrar_ventas')
        ->name('admin.indexmetodopago');

    // PROMOCIONES
    Route::controller(PromocionesController::class)
        ->middleware('permiso:gestion_promociones')
        ->group(function () {
            Route::get('/promociones', 'index')
                ->name('admin.indexpromocion');

            Route::get('/promociones/crear', 'create')
                ->name('admin.crearpromocion');

            Route::post('/promociones/crear/store', 'store')
                ->name('admin.storedpromocion');

            Route::get('/promociones/editar/{id}', 'edit')
                ->name('admin.editpromocion');

            Route::post('/promociones/editar/{id}', 'update')
                ->name('admin.updatepromocion');

            Route::delete('/promociones/eliminar/{id}', 'destroy')
                ->name('admin.destroypromocion');
        });

    // BITÁCORA
    Route::get('/bitacora', [BitacorasController::class, 'index'])
        ->middleware('permiso:consultar_bitacora')
        ->name('admin.indexbitacora');
    // Cambio de contraseña de la cuenta que inició sesión.
Route::middleware('auth')->group(function () {
    Route::get('/mi-contrasena', [
        MiContrasenaController::class,
        'index',
    ])->name('perfil.contrasena');

    Route::put('/mi-contrasena', [
        MiContrasenaController::class,
        'update',
    ])
        ->middleware('throttle:5,1')
        ->name('perfil.contrasena.update');
});    
});Route::middleware('auth')->prefix('admin')->group(function () {
    foreach (['entrada','salida','ajuste'] as $tipo) {
        Route::get('/inventario/'.$tipo, [MovimientoInventarioController::class,'create'])->defaults('tipo',$tipo)->name('admin.movimiento.crear.'.$tipo);
        Route::post('/inventario/'.$tipo, [MovimientoInventarioController::class,'store'])->defaults('tipo',$tipo)->name('admin.movimiento.store.'.$tipo);
    }
    Route::get('/stock-minimo',[StocksController::class,'minimos'])->middleware('permiso:consultar_stock_minimo')->name('admin.stock.minimos');
    Route::get('/agotados',[StocksController::class,'agotados'])->middleware('permiso:ver_agotados')->name('admin.stock.agotados');
    Route::get('/catalogo',[\App\Http\Controllers\CatalogoController::class,'index'])->middleware('permiso:buscar_productos')->name('admin.catalogo');
    Route::get('/pedidos/nuevo',[PedidoController::class,'create'])->middleware('permiso:registrar_pedidos')->name('admin.pedido.crear');
    Route::post('/pedidos/nuevo',[PedidoController::class,'store'])->middleware('permiso:registrar_pedidos')->name('admin.pedido.store');
    Route::get('/pedidos/ver/{id}',[PedidoController::class,'show'])->name('admin.pedido.ver');
});
Route::put('/admin/stocks/{id}/minimo',[StocksController::class,'minimo'])->middleware(['auth','permiso:registrar_ajustes'])->name('admin.stock.minimo');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/pedidos/editar/{id}',[PedidoController::class,'edit'])->middleware('permiso:modificar_pedidos')->name('admin.pedido.editar');
    Route::put('/pedidos/editar/{id}',[PedidoController::class,'update'])->middleware('permiso:modificar_pedidos')->name('admin.pedido.update');
    Route::post('/pedidos/cancelar/{id}',[PedidoController::class,'cancelar'])->middleware('permiso:modificar_pedidos')->name('admin.pedido.cancelar');
    Route::put('/pedidos/estado/{id}',[PedidoController::class,'estado'])->middleware('permiso:cambiar_estado_pedidos')->name('admin.pedido.estado');
    Route::get('/ventas/cobrar/{id}',[VentaController::class,'create'])->middleware('permiso:cobrar_ventas')->name('admin.venta.crear');
    Route::post('/ventas/cobrar/{id}',[VentaController::class,'store'])->middleware('permiso:cobrar_ventas')->name('admin.venta.store');
    Route::post('/promociones/activar/{id}',[PromocionesController::class,'activar'])->middleware('permiso:gestion_promociones')->name('admin.promocion.activar');
    Route::get('/reportes/ventas-diarias',[\App\Http\Controllers\ReportesController::class,'diarias'])->middleware('permiso:ver_reportes')->name('admin.reportes.diarias');
    Route::get('/reportes/metodos-pago',[\App\Http\Controllers\ReportesController::class,'pagos'])->middleware('permiso:ver_reportes')->name('admin.reportes.pagos');
    Route::get('/reportes/inventario',[\App\Http\Controllers\ReportesController::class,'inventario'])->middleware('permiso:ver_reportes')->name('admin.reportes.inventario');
});
Route::get('/admin/bitacora/{id}',[BitacorasController::class,'show'])->middleware(['auth','permiso:consultar_bitacora'])->whereNumber('id')->name('admin.bitacora.ver');
Route::middleware(['auth','permiso:cobrar_ventas'])->prefix('admin')->group(function () {
    Route::get('/ventas/corregir-pedido/{id}',[PedidoController::class,'edit'])->name('admin.venta.pedido.editar');
    Route::put('/ventas/corregir-pedido/{id}',[PedidoController::class,'update'])->name('admin.venta.pedido.update');
});
Route::get('/admin/ventas/siguiente/{id}',[VentaController::class,'siguiente'])->middleware(['auth','permiso:cobrar_ventas'])->whereNumber('id')->name('admin.venta.siguiente');
Route::get('/admin/promociones/disponibles',[PromocionesController::class,'consultar'])->middleware('auth')->name('admin.promociones.consultar');
