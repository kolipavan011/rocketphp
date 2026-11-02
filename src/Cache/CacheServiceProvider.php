<?php

declare(strict_types=1);

namespace Rocket\Cache;

use Rocket\Cache\Contracts\CacheStoreInterface;
use Rocket\Foundation\ServiceProvider;

/**
 * Class CacheServiceProvider
 *
 * Bootstraps the Rocketphp Cache Subsystem.
 */
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('cache', function () {
            return new CacheManager($this->app);
        });

        $this->app->alias('cache', CacheManager::class);

        $this->app->singleton('cache.store', function () {
            /** @var CacheManager $cache */
            $cache = $this->app->make('cache');
            return $cache->store();
        });

        $this->app->alias('cache.store', CacheStoreInterface::class);
    }
}

