<?php

declare(strict_types=1);

namespace Rocket\Security\Contracts;

interface EncrypterContract
{
    /**
     * Encrypt the given value.
     */
    public function encrypt(mixed $value, bool $serialize = true): string;

    /**
     * Decrypt the given payload.
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed;
}

