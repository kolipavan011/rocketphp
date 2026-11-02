<?php

declare(strict_types=1);

namespace Rocket\Security\Validation;

use Exception;

class ValidationException extends Exception
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'The given data was invalid.')
    {
        $this->errors = $errors;
        parent::__construct($message, 422);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}

