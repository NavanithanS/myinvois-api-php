<?php

namespace Nava\MyInvois;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class Cache implements CacheRepository
{
    private $repository;

    /**
     * Create a new Cache instance.
     */
    public function __construct(CacheRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get the cache store instance.
     */
    public static function store(?string $store = null): CacheRepository
    {
        return app('cache')->store($store);
    }

    /**
     * Get an item from the cache.
     *
     * @param  string  $key
     */
    public function get($key, mixed $default = null): mixed
    {
        return $this->repository->get($key, $default);
    }

    /**
     * Store an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  DateTimeInterface|DateInterval|int|null  $ttl
     */
    public function put($key, $value, $ttl = null): bool
    {
        return $this->repository->put($key, $value, $ttl);
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  DateTimeInterface|DateInterval|int|null  $ttl
     */
    public function add($key, $value, $ttl = null): bool
    {
        return $this->repository->add($key, $value, $ttl);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        return $this->repository->increment($key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->repository->decrement($key, $value);
    }

    /**
     * Store an item in the cache forever.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function forever($key, $value): bool
    {
        return $this->repository->forever($key, $value);
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     */
    public function forget($key): bool
    {
        return $this->repository->forget($key);
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): bool
    {
        return $this->repository->flush();
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string
    {
        return $this->repository->getPrefix();
    }

    /**
     * Check if an item exists in the cache.
     *
     * @param  string  $key
     */
    public function has($key): bool
    {
        return $this->repository->has($key);
    }

    /**
     * Get multiple items from the cache.
     *
     * @param  mixed  $default
     */
    public function many(array $keys): array
    {
        return $this->repository->many($keys);
    }

    /**
     * Store multiple items in the cache.
     *
     * @param  DateTimeInterface|DateInterval|int|null  $ttl
     */
    public function putMany(array $values, $ttl = null): bool
    {
        return $this->repository->putMany($values, $ttl);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param  string  $key
     * @param  DateTimeInterface|DateInterval|int|null  $ttl
     */
    public function remember($key, $ttl, \Closure $callback): mixed
    {
        return $this->repository->remember($key, $ttl, $callback);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @param  string  $key
     */
    public function rememberForever($key, \Closure $callback): mixed
    {
        return $this->repository->rememberForever($key, $callback);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param  string  $key
     */
    public function sear($key, \Closure $callback): mixed
    {
        return $this->repository->sear($key, $callback);
    }
}
