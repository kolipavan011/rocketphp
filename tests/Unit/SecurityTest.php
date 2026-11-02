<?php

declare(strict_types=1);

namespace Rocket\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rocket\Container\Container;
use Rocket\Http\Request;
use Rocket\Http\Response;
use Rocket\Security\CsrfTokenManager;
use Rocket\Security\Encrypter;
use Rocket\Security\Hasher;
use Rocket\Security\Middleware\SecureHeaders;
use Rocket\Security\Middleware\ThrottleRequests;
use Rocket\Security\Middleware\VerifyCsrfToken;
use Rocket\Security\RateLimiter;
use Rocket\Security\Session\SessionManager;
use Rocket\Security\Validation\ValidationException;
use Rocket\Security\Validation\Validator;

class SecurityTest extends TestCase
{
    public function test_hasher_argon_or_bcrypt(): void
    {
        $hasher = new Hasher();
        $hash = $hasher->make('supersecretpassword');

        $this->assertNotEmpty($hash);
        $this->assertTrue($hasher->check('supersecretpassword', $hash));
        $this->assertFalse($hasher->check('wrongpassword', $hash));
    }

    public function test_encrypter_aes_256_gcm(): void
    {
        // 32-byte test key
        $key = '12345678901234567890123456789012';
        $encrypter = new Encrypter($key);

        $payload = ['secret' => 'confidential_data', 'code' => 42];
        $encrypted = $encrypter->encrypt($payload);

        $this->assertNotSame(json_encode($payload), $encrypted);

        $decrypted = $encrypter->decrypt($encrypted);
        $this->assertSame($payload, $decrypted);

        // Tampering detection
        $this->expectException(\RuntimeException::class);
        $encrypter->decrypt(substr($encrypted, 0, -4) . 'AAAA');
    }

    public function test_csrf_token_generation_and_validation(): void
    {
        $session = new SessionManager();
        $csrf = new CsrfTokenManager($session);

        $token = $csrf->getToken();
        $this->assertSame(64, strlen($token));
        $this->assertTrue($csrf->validateToken($token));
        $this->assertFalse($csrf->validateToken('invalid-token'));
    }

    public function test_verify_csrf_middleware(): void
    {
        $session = new SessionManager();
        $csrf = new CsrfTokenManager($session);
        $middleware = new VerifyCsrfToken($csrf);

        // GET request is safe and allowed
        $getRequest = Request::create('GET', '/profile');
        $res = $middleware->handle($getRequest, fn () => new Response('Profile OK'));
        $this->assertSame('Profile OK', $res->getContent());

        // POST request without token is rejected with 419
        $postRequest = Request::create('POST', '/profile', ['name' => 'Alice']);
        $res419 = $middleware->handle($postRequest, fn () => new Response('Updated'));
        $this->assertSame(419, $res419->getStatusCode());

        // POST request with valid token is accepted
        $validPost = Request::create('POST', '/profile', [
            'name'   => 'Alice',
            '_token' => $csrf->getToken(),
        ]);
        $res200 = $middleware->handle($validPost, fn () => new Response('Updated'));
        $this->assertSame(200, $res200->getStatusCode());
        $this->assertSame('Updated', $res200->getContent());
    }

    public function test_secure_headers_middleware(): void
    {
        $middleware = new SecureHeaders();
        $request = Request::create('GET', '/');

        $response = $middleware->handle($request, fn () => new Response('OK'));

        $this->assertSame('nosniff', $response->header('x-content-type-options'));
        $this->assertSame('SAMEORIGIN', $response->header('x-frame-options'));
        $this->assertSame('1; mode=block', $response->header('x-xss-protection'));
        $this->assertNotNull($response->header('content-security-policy'));
    }

    public function test_rate_limiter_and_throttle_middleware(): void
    {
        RateLimiter::clear();
        $limiter = new RateLimiter();
        $middleware = new ThrottleRequests($limiter, maxAttempts: 2, decayMinutes: 1);

        $req = Request::create('GET', '/api/data');

        // Request 1: allowed
        $res1 = $middleware->handle($req, fn () => new Response('Data'));
        $this->assertSame(200, $res1->getStatusCode());
        $this->assertSame('1', $res1->header('x-ratelimit-remaining'));

        // Request 2: allowed
        $res2 = $middleware->handle($req, fn () => new Response('Data'));
        $this->assertSame(200, $res2->getStatusCode());
        $this->assertSame('0', $res2->header('x-ratelimit-remaining'));

        // Request 3: blocked (429)
        $res3 = $middleware->handle($req, fn () => new Response('Data'));
        $this->assertSame(429, $res3->getStatusCode());
        $this->assertNotNull($res3->header('retry-after'));
    }

    public function test_validator_rules_and_exception(): void
    {
        $data = [
            'username' => 'alex',
            'email'    => 'alex@test.com',
            'age'      => '25',
            'password' => 'secret123',
            'confirm'  => 'secret123',
        ];

        try {
            $validated = Validator::make($data, [
                'username' => 'required|alpha|min:3',
                'email'    => 'required|email',
                'age'      => 'required|integer|min:18',
                'password' => 'required|min:8',
                'confirm'  => 'required|same:password',
            ])->validate();
        } catch (ValidationException $e) {
            $this->fail("Validation failed unexpectedly: " . json_encode($e->getErrors()));
        }

        $this->assertSame('alex', $validated['username']);
        $this->assertSame('alex@test.com', $validated['email']);

        // Test validation failure
        $badData = ['email' => 'not-an-email', 'age' => 15];
        $validator = Validator::make($badData, [
            'email' => 'required|email',
            'age'   => 'min:18',
        ]);

        $this->assertTrue($validator->fails());
        $errors = $validator->errors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('age', $errors);
    }
}
