<?php

declare(strict_types=1);

namespace Rocket\Foundation;

/**
 * Class ServiceProvider
 *
 * Base class for all framework and application service providers.
 */
abstract class ServiceProvider
{
    /**
     * The application instance.
     */
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Register any application services in the container.
     */
    public function register(): void
    {
        // Override in subclass
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Override in subclass
    }
}

