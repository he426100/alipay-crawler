<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/hello/{name}', function(Request $request, Response $response, $args) {
	$name = $request->getAttribute('name');
	$response->getBody()->write("Hello, $name");

	return $response;
});

// example route to resolve request to uri '/' to \\App\\Http\\Site\\Welcome::index
$app->any('/', function(Request $request, Response $response, $args) use($app) {
	return $app->resolveRoute('\App\Http\Site', 'Welcome', 'index', $args);
});

// example route to resolve request to that matches '/{class}/{method}'
// resolveRoute will try to find a corresponding class::method in a given namespace
$app->any('/{class}/{method}', function(Request $request, Response $response, $args) use($app) {
	return $app->resolveRoute('\App\Http\Site', $args['class'], $args['method'], $args);
});