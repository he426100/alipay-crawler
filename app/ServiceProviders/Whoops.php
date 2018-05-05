<?php

namespace App\ServiceProviders;
use Whoops\Handler\PrettyPageHandler;
use Whoops\RunInterface;
use Whoops\Run;
use NunoMaduro\Collision\Provider as Collision;

class Whoops implements ProviderInterface
{

	public static function register()
	{
		if (app()->console && class_exists(Collision::class)) {
			(new Collision)->register();
		}
		elseif (class_exists(Run::class)) {
			$whoops = new Run;
			$whoops->allowQuit(false);
			$handler = new PrettyPageHandler;
			$whoops->pushHandler($handler);
			$whoops->register();
		}
	}

}