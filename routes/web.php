<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

/** @var \Laravel\Lumen\Routing\Router $router */

$router->post('ping', 'GameController@ping');
$router->post('start', 'GameController@start');
$router->post('move', 'GameController@move');
$router->post('end', 'GameController@end');

