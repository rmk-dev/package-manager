<?php

namespace Rmk\PackageManager;

use Psr\SimpleCache\CacheInterface;
use Rmk\PackageManager\Exception\InvalidCacheKey;

/**
 * Class DefaultCacheAdapter
 *
 * @package Rmk\PackageManager
 */
class DefaultCacheAdapter extends \ArrayObject implements CacheInterface
{

    protected array $ttls = [];

    public function get($key, $default = null)
    {
        $this->validateKey($key);
        if (!$this->offsetExists($key)) {
            return $default;
        }

        return $this->offsetGet($key);
    }

    public function set($key, $value, $ttl = null)
    {
        $this->validateKey($key);
        $this->offsetSet($key, $value);
        $this->ttls[$key] = $value;
    }

    public function delete($key)
    {
        $this->validateKey($key);
        $this->offsetUnset($key);
    }

    public function clear()
    {
        $this->exchangeArray([]);
    }

    public function getMultiple($keys, $default = null)
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->get($key, $default);
        }
    }

    public function setMultiple($values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function has($key)
    {
        $this->validateKey($key);
        return $this->offsetExists($key);
    }

    protected function validateKey(string $key): void
    {
        $regex = '/^[\w\.]{1,64}$/u';
        if (!preg_match($regex, $key)) {
            throw new InvalidCacheKey('Invalid cache key ' . $key);
        }
    }
}
