<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
// Rutas CORS

$routes->get('/', 'Home::index');

// Rutas públicas
$routes->options('/(:any)', 'CorsController::cors');
$routes->group('api', function ($routes) {
  $routes->get('testeoapi', 'Home::testeoapi');
  // $routes->get('test', 'AuthController::test');
  $routes->post('login', 'AuthController::login');
  $routes->post('register', 'AuthController::register');
});

// Rutas protegidas con JWT
$routes->group('api', ['filter' => \App\Filters\AuthFilter::class,], function ($routes) {
  $routes->get('protegido', 'AuthController::testProtegido');
  $routes->get('test', 'AuthController::test');
  $routes->post('transacciones-crear', 'TransaccionesController::create');
  $routes->post('transacciones/(:num)/pagar', 'TransaccionesController::pagar/$1');
  $routes->get('transacciones-mes-actual', 'TransaccionesController::mesActual');
  $routes->get('categorias', 'TransaccionesController::categorias');
  $routes->get('deudaslist', 'TransaccionesController::deudasList');
  $routes->get('transacciones-todas', 'TransaccionesController::allTransacciones');
  $routes->get('deudas-detalle', 'TransaccionesController::deudasDetalle');
  $routes->post('deudas-crear', 'TransaccionesController::createDeuda');
  $routes->get('datos-graficas', 'TransaccionesController::getDatosGraficas');

  // ── Gastos Fijos ──────────────────────────────────────────
  $routes->get('gastos-fijos/plantillas',              'GastosFijosController::plantillas');
  $routes->post('gastos-fijos/plantillas',             'GastosFijosController::crearPlantilla');
  $routes->put('gastos-fijos/plantillas/(:num)',        'GastosFijosController::actualizarPlantilla/$1');
  $routes->delete('gastos-fijos/plantillas/(:num)',     'GastosFijosController::eliminarPlantilla/$1');
  $routes->get('gastos-pendientes',                    'GastosFijosController::pendientes');
  $routes->post('gastos-pendientes',                   'GastosFijosController::crearPendiente');
  $routes->post('gastos-pendientes/generar-mes',       'GastosFijosController::generarMes');
  $routes->patch('gastos-pendientes/(:num)/monto',     'GastosFijosController::actualizarMonto/$1');
  $routes->post('gastos-pendientes/(:num)/pagar',      'GastosFijosController::pagarGasto/$1');
  $routes->delete('gastos-pendientes/(:num)',          'GastosFijosController::eliminarPendiente/$1');

  // ── Tarjetas de Crédito ───────────────────────────────────
  $routes->get('tarjetas',                             'TarjetasController::index');
  $routes->post('tarjetas',                            'TarjetasController::crear');
  $routes->get('tarjetas/(:num)',                      'TarjetasController::detalle/$1');
  $routes->put('tarjetas/(:num)',                      'TarjetasController::actualizar/$1');
  $routes->post('tarjetas/(:num)/gasto',               'TarjetasController::registrarGasto/$1');
  $routes->post('tarjetas/(:num)/pagar',               'TarjetasController::pagarTarjeta/$1');
  $routes->post('tarjetas/(:num)/cuota',               'TarjetasController::registrarCuota/$1');
});

$routes->get("multimedia/(:any)", 'MultimediaController::index/$0');

$routes->group('api/admin', ['filter' => \App\Filters\AdminFilter::class,], function ($routes) {});
