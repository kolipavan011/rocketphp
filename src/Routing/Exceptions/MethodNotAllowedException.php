<?php

declare(strict_types=1);

namespace Rocket\Routing\Exceptions;

use Exception;

class MethodNotAllowedException extends Exception
{
    protected array $allowedMethods;

    public function __construct(array $allowedMethods, string $message = 'Method Not Allowed', int $code = 0, ?\Throwable $previous = null)
    {
        $this->allowedMethods = $allowedMethods;
        parent::__construct($message, $code, $previous);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function getStatusCode(): int
    {
        return 405;
    }
}

