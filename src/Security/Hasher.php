<?php

declare(strict_types=1);

namespace Rocket\Security;

use Rocket\Security\Contracts\HasherContract;
use RuntimeException;
use Throwable;

/**
 * Class Hasher
 *
 * Secure password hasher preferring Argon2id with verified runtime fallback to Bcrypt.
 */
class Hasher implements HasherContract
{
    protected string|int $algo;
    protected array $options;
    protected static ?bool $argonSupported = null;

    public function __construct(string $algo = 'auto', array $options = [])
    {
        if (self::$argonSupported === null) {
            self::$argonSupported = self::testArgonSupport();
        }

        if ($algo === 'auto') {
            $this->algo = self::$argonSupported ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        } else {
            $this->algo = match (strtolower($algo)) {
                'argon2id' => self::$argonSupported ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
                'argon2i'  => defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_BCRYPT,
                'bcrypt'   => PASSWORD_BCRYPT,
                default    => PASSWORD_DEFAULT,
            };
        }

        $this->options = $options;
    }

    /**
     * Runtime self-test to ensure Argon2id hash & verify work on the host C runtime.
     */
    protected static function testArgonSupport(): bool
    {
        if (!defined('PASSWORD_ARGON2ID') || !function_exists('password_algos')) {
            return false;
        }

        if (!in_array('argon2id', password_algos(), true)) {
            return false;
        }

        try {
            $test = password_hash('test', PASSWORD_ARGON2ID, ['time_cost' => 1, 'memory_cost' => 1024]);
            return is_string($test) && password_verify('test', $test);
        } catch (Throwable) {
            return false;
        }
    }

    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, $this->algo, array_merge($this->options, $options));

        if ($hash === false || $hash === null) {
            throw new RuntimeException("Password hashing failed.");
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue): bool
    {
        if ($hashedValue === '') {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, $this->algo, array_merge($this->options, $options));
    }
}
