<?php

namespace App\ServiceProviders;
use League\Plates\Engine;

class Plates implements ProviderInterface
{

	public static function register()
	{
		app()->getContainer()[Engine::class] = function ($c) {
			return function($directory = null, $fileExtension = 'php') {

				$plates = new Engine($directory, $fileExtension);
				$templatesPath = app()->getConfig('settings.templates');
				foreach ($templatesPath as $name => $path) {
					$plates->addFolder($name, $path, true);
				}
				return $plates;
			};
		};
	}

}