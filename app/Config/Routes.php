<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Rutas públicas
$routes->group('api', function ($routes) {
  $routes->get('testeoapi', 'Home::testeoapi');
 $routes->get('test', 'AuthController::test');


});

// Rutas protegidas con JWT
$routes->group('api', ['filter' => \App\Filters\AuthFilter::class,], function ($routes) {
  $routes->post('rifa/create', 'RifaController::create');
 
});

$routes->get("multimedia/(:any)", 'MultimediaController::index/$0');

$routes->group('api/admin', ['filter' => \App\Filters\AdminFilter::class,], function ($routes) {
  $routes->get('detalle-usuario/(:num)', 'AdminController::getUserFullProfile/$1');
  // Suspender usuario
  
});
