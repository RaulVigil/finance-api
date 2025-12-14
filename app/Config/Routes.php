<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Rutas públicas
$routes->group('api', function ($routes) {
  $routes->get('testeoapi', 'Home::testeoapi');
  // $routes->get('test', 'AuthController::test');
  $routes->post('login', 'AuthController::login');
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

});

$routes->get("multimedia/(:any)", 'MultimediaController::index/$0');

$routes->group('api/admin', ['filter' => \App\Filters\AdminFilter::class,], function ($routes) {});
