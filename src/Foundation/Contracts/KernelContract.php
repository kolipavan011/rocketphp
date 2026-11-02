<?php

declare(strict_types=1);

namespace Rocket\Foundation\Contracts;

use Rocket\Http\Request;
use Rocket\Http\Response;

interface KernelContract
{
    /**
     * Bootstrap the application (load environment, configs, providers, exceptions).
     */
    public function bootstrap(): void;

    /**
     * Handle an incoming HTTP request and return a response.
     */
    public function handle(Request $request): Response;

    /**
     * Perform any final actions after the response has been sent.
     */
    public function terminate(Request $request, Response $response): void;
}

