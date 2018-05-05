<?php

namespace App\ServiceProviders;
use Naroga\RedisCache\Redis;
use Predis\Client;
use Psr\SimpleCache\CacheInterface;

class Cache implements ProviderInterface
{

	public static function register()
	{
		app()->getContainer()[CacheInterface::class] = function ($c) {
			return function($driver = 'default') {
				$settings = app()->getConfig('settings.cache');

				return new Redis(new Client($settings[$driver]));
			};
		};
	}

}