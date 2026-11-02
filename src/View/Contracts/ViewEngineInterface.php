<?php

declare(strict_types=1);

namespace Rocket\View\Contracts;

use Rocket\Http\Response;

interface ViewEngineInterface
{
    /**
     * Render a template with data and return raw HTML string.
     *
     * @param string $view Dot notation view path (e.g. 'home' or 'layouts.app')
     * @param array<string, mixed> $data
     * @return string
     */
    public function render(string $view, array $data = []): string;

    /**
     * Render a template and return an HTTP Response.
     */
    public function make(string $view, array $data = [], int $status = 200, array $headers = []): Response;

    /**
     * Add a view directory path.
     */
    public function addPath(string $path, ?string $namespace = null): void;

    /**
     * Share data across all rendered views.
     */
    public function share(string|array $key, mixed $value = null): void;
}

