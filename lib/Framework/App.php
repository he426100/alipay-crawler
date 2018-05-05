<?php

namespace Lib\Framework;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use App\Handlers\Error;
use App\Handlers\PhpError;
use App\Handlers\NotFound;
use Lib\Utils\DotNotation;

class App
{
	public $console = false;

	const DEVELOPMENT = 'development';
	const STAGING = 'staging';
	const PRODUCTION = 'production';
	public static $env = self::DEVELOPMENT;

	/** @var \Slim\App */
	private $slim = null;
	private $providers = [];
	private $settings = [];
	private static $instance = null;


	/**
	 * @param array $settings
	 * @param boolean $console
	 */
	protected function __construct($settings = [], $console = false)
	{
		$this->settings = $settings;
		$this->console = $console;
		$this->slim = new \Slim\App($settings);
		$container = $this->getContainer();
		$displayErrorDetails = $settings['settings']['debug'];

		date_default_timezone_set($settings['settings']['timezone']);

		set_error_handler(function($errno, $errstr, $errfile, $errline) {
			if (!($errno & error_reporting())) {
				return;
			}
			throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
		});

		$container[RequestInterface::class] = $container['request'];
		$container[ResponseInterface::class] = $container['response'];

		$container['errorHandler'] = function() use($displayErrorDetails) {
			return new Error($displayErrorDetails);
		};
		$container['phpErrorHandler'] = function() use($displayErrorDetails) {
			return new PhpError($displayErrorDetails);
		};
		$container['notFoundHandler'] = function() {
			return new NotFound();
		};
	}

	/**
	 * Application Singleton Factory
	 *
	 * @param array $settings
	 * @param boolean $console
	 * @return static
	 */
	final public static function instance($settings = [], $console = false)
	{
		if (null === static::$instance) {
			static::$instance = new static($settings, $console);
		}

		return static::$instance;
	}


	/**
	 * set configuration param
	 *
	 * @return \Interop\Container\ContainerInterface
	 */
	public function getContainer()
	{
		return $this->slim->getContainer();
	}

	/**
	 * set configuration param
	 *
	 * @param string $param
	 * @param mixed $value
	 */
	public function setConfig($param, $value)
	{
		$dn = new DotNotation($this->settings);
		$dn->set($param, $value);
	}

	/**
	 * get configuration param
	 *
	 * @param string $param
	 * @param string $defaultValue
	 * @return mixed
	 */
	public function getConfig($param, $defaultValue = null)
	{
		$dn = new DotNotation($this->settings);
		return $dn->get($param, $defaultValue);
	}

	/**
	 * register providers
	 *
	 * @return void
	 */
	public function registerProviders()
	{
		foreach ($this->getConfig('providers') as $provider) {
			/** @var $provider \App\ServiceProviders\ProviderInterface */
			$provider::register();
		}
	}

	/**
	 * register providers
	 *
	 * @return void
	 */
	public function registerMiddleware()
	{
		foreach (array_reverse($this->getConfig('middleware')) as $middleware) {
			$this->slim->add(new $middleware);
		}
	}


	//proxy all gets to slim
	public function __get($name)
	{
		$c = $this->getContainer();

		if ($c->has($name)) {
			return $c->get($name);
		}
		return $this->resolve($name);
	}

	//proxy all sets to slim
	public function __set($k, $v)
	{
		$this->slim->{$k} = $v;
	}

	// proxy calls to slim
	public function __call($fn, $args = [])
	{
		if (method_exists($this->slim, $fn)) {
			return call_user_func_array([$this->slim, $fn], $args);
		}
		throw new \Exception('Method not found :: '.$fn);
	}


	/**
	 * generate a url
	 *
	 * @param string $url
	 * @param boolean $showIndex pass null to assume config file value
	 * @param boolean $includeBaseUrl
	 * @return string
	 */
	public function url($url = '', $showIndex = null, $includeBaseUrl = true)
	{
		$baseUrl = $includeBaseUrl ? $this->getConfig('settings.baseUrl') : '';

		$indexFile = '';
		if ($showIndex || ($showIndex === null && (bool)$this->getConfig('settings.indexFile'))) {
			$indexFile = 'index.php/';
		}
		if (strlen($url) > 0 && $url[0] == '/') {
			$url = ltrim($url, '/');
		}

		return strtolower($baseUrl.$indexFile.$url);
	}


	/**
	 * return a response object
	 *
	 * @param mixed $resp
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function sendResponse($resp)
	{
		$response = $this->resolve('response');

		if ($resp instanceof ResponseInterface) {
			$response = $resp;
		} elseif (is_array($resp) || is_object($resp)) {
			$response->withJson($resp);
		} else {
			$response->write($resp);
		}

		return $response;
	}


	/**
	 * resolve and call a given class / method
	 *
	 * @param string $namespace
	 * @param string $className
	 * @param string $methodName
	 * @param array $requestParams
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function resolveRoute($namespace = '\App\Http', $className, $methodName, $requestParams = [])
	{
		try {
			$class = new \ReflectionClass($namespace.'\\'.$className);

			if (!$class->isInstantiable() || !$class->hasMethod($methodName)) {
				throw new \ReflectionException("route class is not instantiable or method does not exist");
			}
		} catch (\ReflectionException $e) {
			return $this->notFound();
		}

		$constructorArgs = $this->resolveMethodDependencies($class->getConstructor());
		$controller = $class->newInstanceArgs($constructorArgs);

		$method = $class->getMethod($methodName);
		$args = $this->resolveMethodDependencies($method, $requestParams);

		$ret = $method->invokeArgs($controller, $args);

		return $this->sendResponse($ret);
	}


	/**
	 * resolve a dependency from the container
	 *
	 * @throws \ReflectionException
	 * @param string $name
	 * @param array $params
	 * @param mixed
	 * @return mixed
	 */
	public function resolve($name, $params = [])
	{
		$c = $this->getContainer();
		if ($c->has($name)) {
			return is_callable($c[$name]) ? call_user_func_array($c[$name], $params) : $c[$name];
		}

		if (!class_exists($name)) {
			throw new \ReflectionException("Unable to resolve {$name}");
		}

		$reflector = new \ReflectionClass($name);

		if (!$reflector->isInstantiable()) {
			throw new \ReflectionException("Class {$name} is not instantiable");
		}

		if ($constructor = $reflector->getConstructor()) {
			$dependencies = $this->resolveMethodDependencies($constructor);
			return $reflector->newInstanceArgs($dependencies);
		}

		return new $name();
	}


	/**
	 * resolve dependencies for a given class method
	 *
	 * @param \ReflectionMethod $method
	 * @param array $urlParams
	 * @return array
	 */
	private function resolveMethodDependencies(\ReflectionMethod $method, $urlParams = [])
	{
		return array_map(function ($dependency) use($urlParams) {
			return $this->resolveDependency($dependency, $urlParams);
		}, $method->getParameters());
	}


	/**
	 * resolve a dependency parameter
	 *
	 * @throws \ReflectionException
	 * @param \ReflectionParameter $param
	 * @param array $urlParams
	 * @return mixed
	 */
	private function resolveDependency(\ReflectionParameter $param, $urlParams = [])
	{
		// for controller method para injection from $_GET
		if (count($urlParams) && array_key_exists($param->name, $urlParams)) {
			return $urlParams[$param->name];
		}

		// param is instantiable
		if ($param->isDefaultValueAvailable()) {
			return $param->getDefaultValue();
		}

		if (!$param->getClass()) {
			throw new \ReflectionException("Unable to resolve method param {$param->name}");
		}

		// try to resolve from container
		return $this->resolve($param->getClass()->name);
	}


	/**
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function notFound()
	{
		$handler = $this->getContainer()['notFoundHandler'];
		return $handler($this->getContainer()['request'], $this->getContainer()['response']);
	}

}