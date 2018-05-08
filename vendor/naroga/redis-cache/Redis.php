<?php

namespace Naroga\RedisCache;

use Naroga\RedisCache\Exception\InvalidArgumentException;
use Naroga\RedisCache\Exception\TransactionFailedException;
use Predis\Client;
use Predis\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\DateInterval;
use Traversable;

/**
 * Class Redis
 * @package Naroga\RedisCache
 */
class Redis implements CacheInterface
{
    /** @var Client */
    private $client;

    /**
     * Redis constructor.
     *
     * @param ClientInterface $client A Predis Client.
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /** @inheritDoc */
    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException("Provided key is not a legal string.");
        }

        $item = unserialize($this->client->get($this->canonicalize($key)));

        if (!empty($item)) {
            return $item;
        } else {
            return $default;
        }
    }

    /** @inheritDoc */
    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException("Provided key is not a legal string.");
        }

        $value = serialize($value);

        if ($ttl === null) {
            return $this->client->set($this->canonicalize($key), $value) == 'OK';
        }

        if ($ttl instanceof \DateInterval) {
            return $this
                    ->client
                    ->setex(
                        $this->canonicalize($key),
                        $ttl->s,
                        $value
                    ) == 'OK';
        }

        if (is_integer($ttl)) {
            return $this
                    ->client
                    ->setex(
                        $this->canonicalize($key),
                        $ttl,
                        $value
                    ) == 'OK';
        }

        throw new InvalidArgumentException("TTL must be an integer or an instance of \\DateInterval");
    }

    /** @inheritDoc */
    public function delete($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException("Provided key is not a legal string.");
        }

        return $this->client->del($this->canonicalize($key)) == 1;
    }

    /** @inheritDoc */
    public function clear()
    {
        $this->client->flushdb();

        return true; // FlushDB never fails.
    }

    /** @inheritDoc */
    public function getMultiple($keys, $default = null)
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new InvalidArgumentException("Keys must be an array or a \\Traversable instance.");
        }

        $result = array();
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /** @inheritDoc */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values) && !$values instanceof Traversable) {
            throw new InvalidArgumentException("Values must be an array or a \\Traversable instance.");
        }

        try {
            $redis = $this;
            $responses = $this->client->transaction(function ($tx) use ($values, $ttl, $redis) {
                foreach ($values as $key => $value) {
                    if (!$redis->set($key, $value, $ttl)) {
                        throw new TransactionFailedException();
                    }
            }});
        } catch (TransactionFailedException $e) {
            return false;
        }

        return true;
    }

    /** @inheritDoc */
    public function deleteMultiple($keys)
    {
        if (!is_array($keys) && !$keys instanceof Traversable) {
            throw new InvalidArgumentException("Keys must be an array or a \\Traversable instance.");
        }

        try {
            $redis = $this;
            $transaction = $this->client->transaction(function ($tx) use ($keys, $redis) {
                foreach ($keys as $key) {
                    if (!$redis->delete($key)) {
                        throw new TransactionFailedException();
                    }
             }});
        } catch (TransactionFailedException $e) {
            return false;
        }

        return true;
    }

    /** @inheritDoc */
    public function has($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException("Provided key is not a legal string.");
        }

        return $this->client->exists($this->canonicalize($key)) === 1;
    }

    /**
     * Canonicalizes a string.
     *
     * In practice, it replaces whitespaces for underscores, as PSR-16 defines we must allow
     * any valid PHP string, and Redis won't allow key names with whitespaces.
     *
     * @param string $string String to be canonicalized
     * @return string Canonical string
     */
    private function canonicalize($string)
    {
        return str_replace(' ', '_', $string);
    }
}
