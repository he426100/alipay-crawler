# Redis Cache

[![Build status](https://travis-ci.org/naroga/redis-cache.svg?branch=master)](https://travis-ci.org/naroga/redis-cache) [![Coverage Status](https://coveralls.io/repos/github/naroga/redis-cache/badge.svg?branch=master)](https://coveralls.io/github/naroga/redis-cache?branch=master)

This is a simple Redis driver that implements PSR-16 compatible with PHP 5.3.3+.

## Installation

Install using composer:

    $ composer require naroga/redis-cache
   
That's it.

`naroga/redis-cache` adheres to [SemVer](http://semver.org/).

## Usage

`naroga/redis-cache` should be constructed with a `predis/predis` client.

You can check [Predis' documentation here](https://github.com/nrk/predis#connecting-to-redis).

```php
<?php

require_once "vendor/autoload.php";

use Naroga\RedisCache\Redis;
use Predis\Client;

$config = array(
    'scheme' => 'tcp',
    'host' => 'localhost',
    'port' => 6379
);

$redis = new Redis(new Client($config));

if (!$redis->has('myKey')) {
    $redis->set('myKey', 'myValue', 1800); //Just call any PSR-16 methods here.
}
```

## License

This library is released under the MIT license. Check [LICENSE.md](LICENSE.md) for more information .
