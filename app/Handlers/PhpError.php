<?php

namespace App\Handlers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use League\Plates\Engine;
use NunoMaduro\Collision\Provider as Collision;

final class PhpError extends \Slim\Handlers\PhpError
{

	public function __invoke(Request $request, Response $response, \Throwable $error)
	{
		$app = app();
		$container = $app->getContainer();

		// Log the message
		if ($container->has(LoggerInterface::class)) {
			$app->resolve(LoggerInterface::class)->critical($error);
		}

		if ($app->console && !class_exists(Collision::class)) {
			return $response
				->withStatus(500)
				->withHeader('Content-type', 'text/plain')
				->write("Exception: {$error->getMessage()} \n\n {$error->getTraceAsString()}");
		}

		if ($this->determineContentType($request) == 'text/html') {
			if (!$this->displayErrorDetails) {
				$resp = $app->resolve(Engine::class)->render('error::500', ['message' => $error->getMessage()]);
				return $response->withStatus(500)->write($resp);
			}

			throw $error;
		}

		return parent::__invoke($request, $response, $error);
	}

	protected function renderJsonErrorMessage(\Throwable $error)
	{
		$error = ['message' => $error->getMessage()];

		if ($this->displayErrorDetails) {
			$error['exception'] = [];

			do {
				$error['exception'][] = [
					'type' => get_class($error),
					'code' => $error->getCode(),
					'message' => $error->getMessage(),
					'file' => $error->getFile(),
					'line' => $error->getLine(),
					'trace' => explode("\n", $error->getTraceAsString()),
				];
			} while ($error = $error->getPrevious());
		}

		return json_encode($error, JSON_PRETTY_PRINT);
	}
}