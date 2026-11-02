<?php

declare(strict_types=1);

namespace App\Providers;

use Rocket\Foundation\ServiceProvider;

/**
 * Class AppServiceProvider
 *
 * Central application service provider for registering and bootstrapping custom services.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services in the container.
     */
    public function register(): void
    {
        // Bind application domain services, interfaces, and singletons here.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Perform post-registration boot logic (e.g. view composers, model observers, macro setup).
    }
}

