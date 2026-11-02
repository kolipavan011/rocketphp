<?php

declare(strict_types=1);

namespace Rocket\Security;

use Rocket\Security\Contracts\CsrfTokenManagerContract;
use Rocket\Security\Session\SessionManager;

/**
 * Class CsrfTokenManager
 *
 * Cryptographically secure CSRF token generator and validator.
 */
class CsrfTokenManager implements CsrfTokenManagerContract
{
    protected ?SessionManager $session;
    protected ?string $token = null;

    public function __construct(?SessionManager $session = null)
    {
        $this->session = $session;
    }

    public function getToken(): string
    {
        if ($this->session !== null) {
            $sessionToken = $this->session->get('_csrf_token');
            if (is_string($sessionToken) && strlen($sessionToken) === 64) {
                return $sessionToken;
            }

            return $this->regenerateToken();
        }

        if ($this->token === null) {
            $this->token = bin2hex(random_bytes(32));
        }

        return $this->token;
    }

    public function regenerateToken(): string
    {
        $this->token = bin2hex(random_bytes(32));

        if ($this->session !== null) {
            $this->session->set('_csrf_token', $this->token);
        }

        return $this->token;
    }

    public function validateToken(?string $token): bool
    {
        if ($token === null || strlen($token) !== 64) {
            return false;
        }

        return hash_equals($this->getToken(), $token);
    }
}

