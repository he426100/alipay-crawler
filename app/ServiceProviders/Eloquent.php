<?php 

namespace App\ServiceProviders;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;

class Eloquent implements ProviderInterface
{

	public static function register()
	{
		$dbSettings = app()->getConfig('settings.database');

		// register connections
		$capsule = new Capsule;
		foreach ($dbSettings as $name => $configs) {
			$capsule->addConnection($dbSettings[$name], $name);
		}
		
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

		app()->getContainer()[ConnectionInterface::class] = function ($c) {
			return function($driver = 'default') {
				$conn = Capsule::connection($driver);
				if ($conn->getConfig('profiling') == true) {
					$conn->enableQueryLog();
				}
				
				return $conn;
			};
		};
	}
}