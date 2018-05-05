<?php

namespace App\Handlers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use League\Plates\Engine;

final class NotFound extends \Slim\Handlers\NotFound
{

	public function __invoke(Request $request, Response $response)
	{
		$app = app();
		$container = $app->getContainer();

		// Log the message
		if ($container->has(LoggerInterface::class)) {
			$app->resolve(LoggerInterface::class)->error("URI '".$request->getUri()->getPath()."' not found");
		}

		if ($app->console) {
			return $response->write("Error: request does not match any command::method or mandatory params are not properly set\n");
		}

		$resp = $app->resolve(Engine::class)->render('error::404');
		$response->withStatus(404)->write($resp);

		return $response;
	}



}