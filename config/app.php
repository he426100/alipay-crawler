<?php
return [
	'settings' => [
		'environment' => 'development',
		'addContentLengthHeader' => false,
		// default timezone
		'timezone' => 'Europe/Lisbon',
		// Only set this if you need access to route within middleware
		'determineRouteBeforeAppMiddleware' => false,
		// log file path
		'appLogFilePath' => STORAGE_PATH."logs".DS."app_".date('Ymd').".log",
		// template folders
		'templates' => [
			'error' => RESOURCES_PATH."views".DS."http".DS."error",
			'console' => RESOURCES_PATH."views".DS."console",
			'site' 	=> RESOURCES_PATH."views".DS."http".DS."site",
			'mail' 	=> RESOURCES_PATH."views".DS."mail",
		],
		'session' => [
			'name' => 'app',
			'lifetime' => 7200,
			'path' => '/',
			'domain' => null,
			'secure' => false,
			'httponly' => true,
			'cache_limiter' => 'nocache',
			'filesPath' => STORAGE_PATH.'sessions',
		],
		// storage settings
		'filesystem' => [
			'local' => [
				'driver' 	=> 'local',
				'root'   	=> STORAGE_PATH,
			],
			'ftp' => [
				'driver'	=> 'ftp',
				'host' 		=> '',
				'username' 	=> '',
				'password' 	=> '',
				'port' 		=> 21,
				'root' 		=> '/',
				'passive' 	=> true,
				'ssl' 		=> false,
				'timeout' 	=> 30,
			],
		],
		'mail' => [
			'default' => [
				'host'    	=> '',
				'port'      => 25,
				'secure'	=> '',
				'username'  => '',
				'password'  => '',
				'from'		=> '',
				'fromName'	=> '',
				'replyTo'	=> '',
			]
		],
	],
	// add your service providers here
	// providers bellow are ALWAYS added
	'providers' => [
		App\ServiceProviders\Whoops::class,
		App\ServiceProviders\Monolog::class,
		App\ServiceProviders\Plates::class,
		App\ServiceProviders\Eloquent::class,
		App\ServiceProviders\FileSystem::class,
		App\ServiceProviders\Mailer::class,
		App\ServiceProviders\Cache::class,
	],
	// add your middleware here
	// middleware bellow are called for every route
	'middleware' => [
		App\Middleware\Session::class,
	],

];
