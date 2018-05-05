<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

// automatic console command resolver
$app->get('/{command}/{method}', function (Request $request, Response $response, $args) use ($app, $argv) {

	$params = [];
	for ($i=2; $i<count($argv); ++$i) {
		$parts = explode("=", $argv[$i]);
		$params[$parts[0]] = $parts[1];
	}

	$response->withHeader('Content-Type', 'text/plain');

	return $app->resolveRoute("\\App\\Console", $argv[0], $argv[1], $params);
});

// help route to display available command in
$app->get('/help', function (Request $request, Response $response, $args) {
	$response->withHeader('Content-Type', 'text/plain');

	$response->write("\n** Slim command line **\n\n");
	$response->write("usage: php ".ROOT_PATH."cli.php <command-name> <method-name> [parameters...]\n\n");
	$response->write("The following commands are available:\n");

	$iterator = new DirectoryIterator(APP_PATH.'Console');
	foreach ($iterator as $fileinfo) {
		if ($fileinfo->isFile()) {
			$className = str_replace(".php", "", $fileinfo->getFilename());
			$class = new \ReflectionClass("\\App\\Console\\$className");

			if (!$class->isAbstract()) {
				$response->write("- ".strtolower($className)."\n");

				foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
					if (strpos($method->getName(), '__') === 0) {
						continue;
					}
					$response->write("       ".strtolower($method->getName())." ");
					foreach ($method->getParameters() as $parameter) {
						if ($parameter->isDefaultValueAvailable()) {
							$response->write("[".$parameter->getName()."=value] ");
						}
						else {
							$response->write($parameter->getName()."=value ");
						}
					}
					$response->write("\n");
				}
				$response->write("\n");
			}
		}
	}

	return $response;
});