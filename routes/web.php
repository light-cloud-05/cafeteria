<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\RolPermisosController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\StocksController;
use App\Http\Controllers\MovimientoInventarioController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\DetallePedidoController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\CierreCajasController;
use App\Http\Controllers\PromocionesController;
use App\Http\Controllers\MetodoPagosController;
use App\Http\Controllers\BitacorasController;

/*
|--------------------------------------------------------------------------
| Rutas Web - Sistema KARMA Cafetería
|--------------------------------------------------------------------------
*/

// Rutas disponibles para personas que no han iniciado sesión.
Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'index'])
        ->name('login');

    Route::post('/login', [SessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

// Rutas que requieren una sesión iniciada.
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('home');
    });

    Route::get('/home', function () {
        return view('welcome');
    })->name('home');

    Route::post('/logout', [SessionController::class, 'destroy'])
        ->name('logout');
});
/*/////////// 1. GESTIÓN DE USUARIOS, ROLES Y PERMISOS //////////// */

// Usuarios
Route::controller(UserController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/usuarios', 'index')->name('admin.indexusuario');
    Route::get('/admin/usuarios/crear', 'create')->name('admin.crearusuario');
    Route::post('/admin/usuarios/crear/store', 'store')->name('admin.storedusuario');
    Route::get('/admin/usuarios/editar/{usuario}', 'edit')->name('admin.editusuario');
    Route::post('/admin/usuarios/editar/{usuario}', 'update')->name('admin.updateusuario');
    Route::get('/admin/usuarios/eliminar/{usuario}', 'destroy')->name('admin.destroyusuario');
});

// Roles
Route::controller(RolController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/roles', 'index')->name('admin.indexrol');
    Route::get('/admin/roles/crear', 'create')->name('admin.crearrol');
    Route::post('/admin/roles/crear/store', 'store')->name('admin.storedrol');
    Route::get('/admin/roles/editar/{id}', 'edit')->name('admin.editrol');
    Route::post('/admin/roles/editar/{id}', 'update')->name('admin.updaterol');
    Route::get('/admin/roles/eliminar/{id}', 'destroy')->name('admin.destroyrol');
});

// Permisos y Asignación
Route::get('/admin/permisos', [PermisoController::class, 'index'])->middleware('auth.admin')->name('admin.indexpermiso');

Route::controller(RolPermisosController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/rol-permisos', 'index')->name('admin.indexrolpermiso');
    Route::post('/admin/rol-permisos/store', 'store')->name('admin.storedrolpermiso');
});


/*/////////// 2. GESTIÓN DE PRODUCTOS Y CATEGORÍAS //////////// */

// Productos
Route::controller(ProductoController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/productos', 'index')->name('admin.indexproducto');
    Route::get('/admin/productos/crear', 'create')->name('admin.crearproducto');
    Route::post('/admin/productos/crear/store', 'store')->name('admin.storedproducto');
    Route::get('/admin/productos/editar/{id}', 'edit')->name('admin.editproducto');
    Route::post('/admin/productos/editar/{id}', 'update')->name('admin.updateproducto');
    Route::get('/admin/productos/eliminar/{id}', 'destroy')->name('admin.destroyproducto');
});

// Categorías
Route::controller(CategoriaController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/categorias', 'index')->name('admin.indexcategoria');
    Route::get('/admin/categorias/crear', 'create')->name('admin.crearcategoria');
    Route::post('/admin/categorias/crear/store', 'store')->name('admin.storedcategoria');
    Route::get('/admin/categorias/editar/{id}', 'edit')->name('admin.editarcategoria');
    Route::post('/admin/categorias/editar/{id}', 'update')->name('admin.updatecategoria');
    Route::get('/admin/categorias/eliminar/{id}', 'destroy')->name('admin.destroycategoria');
});


/*/////////// 3. PROVEEDORES E INVENTARIO //////////// */

// Proveedores
Route::controller(ProveedorController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/proveedores', 'index')->name('admin.indexproveedor');
    Route::get('/admin/proveedores/crear', 'create')->name('admin.crearproveedor');
    Route::post('/admin/proveedores/crear/store', 'store')->name('admin.storedproveedor');
    Route::get('/admin/proveedores/editar/{id}', 'edit')->name('admin.editproveedor');
    Route::post('/admin/proveedores/editar/{id}', 'update')->name('admin.updateproveedor');
    Route::get('/admin/proveedores/eliminar/{id}', 'destroy')->name('admin.destroyproveedor');
});

// Stocks
Route::controller(StocksController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/stocks', 'index')->name('admin.indexstock');
    Route::get('/admin/stocks/editar/{id}', 'edit')->name('admin.editstock');
    Route::post('/admin/stocks/editar/{id}', 'update')->name('admin.updatestock');
});

// Movimiento de Inventario
Route::controller(MovimientoInventarioController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/movimientos', 'index')->name('admin.indexmovimiento');
    Route::get('/admin/movimientos/crear', 'create')->name('admin.crearmovimiento');
    Route::post('/admin/movimientos/crear/store', 'store')->name('admin.storedmovimiento');
});


/*/////////// 4. PEDIDOS, VENTAS Y CAJA //////////// */

// Pedidos (y su detalle)
Route::controller(PedidoController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/pedidos', 'index')->name('admin.indexpedido');
    Route::get('/admin/pedidos/crear', 'create')->name('admin.crearpedido');
    Route::post('/admin/pedidos/crear/store', 'store')->name('admin.storedpedido');
    Route::get('/admin/pedidos/editar/{id}', 'edit')->name('admin.editpedido');
    Route::post('/admin/pedidos/editar/{id}', 'update')->name('admin.updatepedido');
    Route::get('/admin/pedidos/eliminar/{id}', 'destroy')->name('admin.destroypedido');
});

// Ventas
Route::controller(VentaController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/ventas', 'index')->name('admin.indexventa');
    Route::get('/admin/ventas/crear', 'create')->name('admin.crearventa');
    Route::post('/admin/ventas/crear/store', 'store')->name('admin.storedventa');
});

// Cierre de Cajas
Route::controller(CierreCajasController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/cierre-caja', 'index')->name('admin.indexcierrecaja');
    Route::post('/admin/cierre-caja/store', 'store')->name('admin.storedcierrecaja');
});

// Métodos de Pago
Route::get('/admin/metodos-pago', [MetodoPagosController::class, 'index'])->middleware('auth.admin')->name('admin.indexmetodopago');


/*/////////// 5. PROMOCIONES Y BITÁCORA //////////// */

// Promociones
Route::controller(PromocionesController::class)->middleware('auth.admin')->group(function () {
    Route::get('/admin/promociones', 'index')->name('admin.indexpromocion');
    Route::get('/admin/promociones/crear', 'create')->name('admin.crearpromocion');
    Route::post('/admin/promociones/crear/store', 'store')->name('admin.storedpromocion');
    Route::get('/admin/promociones/editar/{id}', 'edit')->name('admin.editpromocion');
    Route::post('/admin/promociones/editar/{id}', 'update')->name('admin.updatepromocion');
    Route::get('/admin/promociones/eliminar/{id}', 'destroy')->name('admin.destroypromocion');
});

// Bitácora / Logs
Route::get('/admin/bitacora', [BitacorasController::class, 'index'])->middleware('auth.admin')->name('admin.indexbitacora');