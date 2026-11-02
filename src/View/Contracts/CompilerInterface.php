<?php

declare(strict_types=1);

namespace Rocket\View\Contracts;

interface CompilerInterface
{
    /**
     * Compile a template string into PHP code.
     */
    public function compileString(string $value): string;

    /**
     * Compile a template file into a cached PHP file and return the compiled file path.
     */
    public function compile(string $path): string;

    /**
     * Determine if the view file needs recompilation.
     */
    public function isExpired(string $path): bool;

    /**
     * Register a custom directive.
     */
    public function directive(string $name, callable $handler): void;
}

