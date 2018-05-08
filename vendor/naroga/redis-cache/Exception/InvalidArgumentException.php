<?php

namespace Naroga\RedisCache\Exception;

use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

class InvalidArgumentException extends \Exception implements SimpleCacheInvalidArgumentException
{
}
