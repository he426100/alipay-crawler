<?php

namespace Naroga\RedisCache\Exception;

use Psr\SimpleCache\CacheException;

class TransactionFailedException extends \Exception implements CacheException
{
}
