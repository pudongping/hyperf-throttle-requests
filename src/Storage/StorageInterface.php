<?php
/**
 *
 *
 * Created by PhpStorm
 * User: Alex
 * Date: 2023-06-17 20:05
 */
declare(strict_types=1);

namespace Pudongping\HyperfThrottleRequests\Storage;

interface StorageInterface
{

    /**
     * Fetches a value from the cache.
     *
     * @param string $key
     * @param $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Store an item in the cache.
     *
     * @param string $key
     * @param string $value
     * @param int|null $ttl
     * @return bool
     */
    public function put(string $key, string $value, ?int $ttl = null): bool;

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param string $key
     * @param string $value
     * @param int|null $ttl
     * @return bool
     */
    public function add(string $key, string $value, ?int $ttl = null): bool;

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param int $value
     * @return int
     */
    public function increment(string $key, int $value = 1): int;

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove all has common prefix items from the cache.
     *
     * @param string $prefix
     * @return bool
     */
    public function clearPrefix(string $prefix): bool;

}