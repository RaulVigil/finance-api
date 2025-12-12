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
   $routes->get('transacciones-mes-actual', 'TransaccionesController::mesActual');
});

$routes->get("multimedia/(:any)", 'MultimediaController::index/$0');

$routes->group('api/admin', ['filter' => \App\Filters\AdminFilter::class,], function ($routes) {});
