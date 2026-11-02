<?php

declare(strict_types=1);

namespace Rocket\Foundation;

use Rocket\Container\Container;

/**
 * Class Application
 *
 * Core application container and bootstrap manager for Rocketphp.
 */
class Application extends Container
{
    /**
     * Framework version.
     */
    public const VERSION = '1.0.0';

    /**
     * Base path of the application.
     */
    protected string $basePath;

    /**
     * Indicates if the application has booted.
     */
    protected bool $booted = false;

    /**
     * List of registered service provider instances.
     *
     * @var array<int, ServiceProvider>
     */
    protected array $serviceProviders = [];

    /**
     * List of loaded service provider class names.
     *
     * @var array<string, bool>
     */
    protected array $loadedProviders = [];

    public function __construct(?string $basePath = null)
    {
        $this->setBasePath($basePath ?? (getcwd() ?: '.'));
        $this->registerBaseBindings();
    }

    /**
     * Set the application base path and initialize standard directory mappings.
     */
    public function setBasePath(string $basePath): static
    {
        $this->basePath = rtrim($basePath, '\/');
        $this->instance('path.base', $this->basePath);
        $this->instance('path.app', $this->basePath . DIRECTORY_SEPARATOR . 'app');
        $this->instance('path.config', $this->basePath . DIRECTORY_SEPARATOR . 'config');
        $this->instance('path.public', $this->basePath . DIRECTORY_SEPARATOR . 'public');
        $this->instance('path.storage', $this->basePath . DIRECTORY_SEPARATOR . 'storage');
        $this->instance('path.resources', $this->basePath . DIRECTORY_SEPARATOR . 'resources');
        $this->instance('path.database', $this->basePath . DIRECTORY_SEPARATOR . 'database');

        return $this;
    }

    /**
     * Register the core container bindings.
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->instance(static::class, $this);
    }

    /**
     * Get path helpers.
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : '');
    }

    public function appPath(string $path = ''): string
    {
        return $this->basePath('app' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : ''));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : ''));
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : ''));
    }

    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : ''));
    }

    public function resourcePath(string $path = ''): string
    {
        return $this->basePath('resources' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : ''));
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '\/') : ''));
    }

    /**
     * Register a service provider.
     */
    public function register(ServiceProvider|string $provider): ServiceProvider
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        $providerClass = get_class($provider);

        if (isset($this->loadedProviders[$providerClass])) {
            return $provider;
        }

        $this->loadedProviders[$providerClass] = true;
        $provider->register();
        $this->serviceProviders[] = $provider;

        if ($this->booted) {
            $provider->boot();
        }

        return $provider;
    }

    /**
     * Boot the application's service providers.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->bootstrapConfig();

        foreach ($this->serviceProviders as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    /**
     * Bootstrap the configuration repository.
     */
    public function bootstrapConfig(): void
    {
        if ($this->bound('config')) {
            return;
        }

        $envFile = $this->basePath('.env');
        if (file_exists($envFile)) {
            Env::load($envFile);
        }

        $config = new Config();
        $config->loadFromDirectory($this->configPath());
        $this->instance('config', $config);
        $this->instance(Config::class, $config);
    }

    /**
     * Determine if the application has booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get the current application environment.
     */
    public function environment(): string
    {
        return (string) Env::get('APP_ENV', 'production');
    }

    /**
     * Check if running in production.
     */
    public function isProduction(): bool
    {
        return $this->environment() === 'production';
    }

    /**
     * Check if running in testing environment.
     */
    public function isTesting(): bool
    {
        return $this->environment() === 'testing';
    }

    /**
     * Terminate the application cycle and flush scoped state.
     */
    public function terminate(): void
    {
        $this->flushScoped();
    }
}
