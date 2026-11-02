<?php

declare(strict_types=1);

namespace Rocket\View;

use Rocket\Foundation\ServiceProvider;
use Rocket\View\Contracts\CompilerInterface;
use Rocket\View\Contracts\ViewEngineInterface;

/**
 * Class ViewServiceProvider
 *
 * Configures and registers the Rocket View Engine and Compiler.
 */
class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('view.compiler', function () {
            $cachePath = $this->app->storagePath('cache/views');
            return new Compiler($cachePath);
        });
        $this->app->alias('view.compiler', CompilerInterface::class);

        $this->app->singleton('view', function () {
            $compiler = $this->app->make('view.compiler');
            $viewsPath = $this->app->resourcePath('views');
            return new ViewEngine($compiler, [$viewsPath], $this->app);
        });
        $this->app->alias('view', ViewEngineInterface::class);
        $this->app->alias('view', ViewEngine::class);
    }
}

