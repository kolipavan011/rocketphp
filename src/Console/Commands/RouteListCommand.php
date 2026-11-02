<?php

declare(strict_types=1);

namespace Rocket\Console\Commands;

use Rocket\Console\Command;
use Rocket\Routing\Router;

class RouteListCommand extends Command
{
    protected string $name = 'route:list';
    protected string $description = 'List all registered HTTP routes';

    public function handle(array $arguments, array $options): int
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $routes = $router->getRoutes();

        if (empty($routes)) {
            $this->warn('No routes registered.');
            return 0;
        }

        $headers = ['Method', 'URI', 'Name', 'Action', 'Middleware'];
        $rows = [];

        foreach ($routes as $route) {
            $action = $route->getAction();
            $actionStr = 'Closure';

            if (is_array($action)) {
                $actionStr = (is_object($action[0]) ? get_class($action[0]) : $action[0]) . '@' . $action[1];
            } elseif (is_string($action)) {
                $actionStr = $action;
            }

            $rows[] = [
                'method'     => implode('|', $route->getMethods()),
                'uri'        => $route->getUri(),
                'name'       => $route->getName() ?? '-',
                'action'     => $actionStr,
                'middleware' => implode(', ', $route->getMiddleware()) ?: '-',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}

