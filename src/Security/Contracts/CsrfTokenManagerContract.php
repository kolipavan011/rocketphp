<?php

declare(strict_types=1);

namespace Rocket\Security\Contracts;

interface CsrfTokenManagerContract
{
    /**
     * Get the current CSRF token or generate a new one.
     */
    public function getToken(): string;

    /**
     * Regenerate the CSRF token.
     */
    public function regenerateToken(): string;

    /**
     * Validate an incoming CSRF token.
     */
    public function validateToken(?string $token): bool;
}

