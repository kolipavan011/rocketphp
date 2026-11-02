<?php

declare(strict_types=1);

namespace Rocket\Routing\Contracts;

use Closure;

interface RouteContract
{
    public function getMethods(): array;
    public function getUri(): string;
    public function getAction(): array|callable|string;
    public function getName(): ?string;
    public function name(string $name): static;
    public function getMiddleware(): array;
    public function middleware(array|string $middleware): static;
    public function where(array|string $name, ?string $pattern = null): static;
    public function whereNumber(string ...$parameters): static;
    public function whereAlpha(string ...$parameters): static;
    public function getConstraints(): array;
    public function matches(string $method, string $path): bool;
    public function extractParameters(string $path): array;
}

