<?php

declare(strict_types=1);

namespace Rocket\Security\Contracts;

interface HasherContract
{
    /**
     * Hash the given value using Argon2id or Bcrypt.
     */
    public function make(string $value, array $options = []): string;

    /**
     * Check the given plain value against a hash with timing-attack mitigation.
     */
    public function check(string $value, string $hashedValue): bool;

    /**
     * Check if the given hash has been hashed using the given options.
     */
    public function needsRehash(string $hashedValue, array $options = []): bool;
}

