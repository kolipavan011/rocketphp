<?php

declare(strict_types=1);

namespace Rocket\Security;

use Rocket\Foundation\ServiceProvider;
use Rocket\Security\Contracts\CsrfTokenManagerContract;
use Rocket\Security\Contracts\EncrypterContract;
use Rocket\Security\Contracts\HasherContract;
use Rocket\Security\Session\SessionManager;

/**
 * Class SecurityServiceProvider
 *
 * Bootstraps security, hashing, encryption, session, and rate-limiting services.
 */
class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('security.hasher', function () {
            return new Hasher();
        });
        $this->app->alias('security.hasher', HasherContract::class);
        $this->app->alias('security.hasher', Hasher::class);

        $this->app->singleton('security.encrypter', function () {
            $key = (string) $this->app->make('config')->get('app.key', 'base64:4v7g7F9W1eM+vK3QvL/bXkL1jZ2a0B3d8C5eF7gH9jI=');
            return new Encrypter($key);
        });
        $this->app->alias('security.encrypter', EncrypterContract::class);
        $this->app->alias('security.encrypter', Encrypter::class);

        $this->app->singleton('session', function () {
            return new SessionManager();
        });
        $this->app->alias('session', SessionManager::class);

        $this->app->singleton('security.csrf', function () {
            return new CsrfTokenManager($this->app->make('session'));
        });
        $this->app->alias('security.csrf', CsrfTokenManagerContract::class);
        $this->app->alias('security.csrf', CsrfTokenManager::class);

        $this->app->singleton('security.limiter', function () {
            return new RateLimiter();
        });
        $this->app->alias('security.limiter', RateLimiter::class);
    }
}

