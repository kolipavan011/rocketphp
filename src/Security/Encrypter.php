<?php

declare(strict_types=1);

namespace Rocket\Security;

use Rocket\Security\Contracts\EncrypterContract;
use RuntimeException;

/**
 * Class Encrypter
 *
 * AES-256-GCM Authenticated Encryption with AEAD integrity verification.
 */
class Encrypter implements EncrypterContract
{
    protected string $key;
    protected string $cipher = 'aes-256-gcm';

    public function __construct(string $key)
    {
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        if (strlen($key) !== 32) {
            throw new RuntimeException("Encryption key must be exactly 32 bytes for AES-256-GCM.");
        }

        $this->key = $key;
    }

    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $iv = random_bytes(12);
        $tag = '';

        $formatted = $serialize ? serialize($value) : (string) $value;

        $ciphertext = openssl_encrypt(
            $formatted,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException("Could not encrypt the data.");
        }

        $json = json_encode([
            'iv'    => base64_encode($iv),
            'value' => base64_encode($ciphertext),
            'tag'   => base64_encode($tag),
            's'     => $serialize,
        ]);

        return base64_encode($json);
    }

    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $decodedJson = base64_decode($payload, true);
        if ($decodedJson === false) {
            throw new RuntimeException("Invalid payload.");
        }

        $data = json_decode($decodedJson, true);
        if (!isset($data['iv'], $data['value'], $data['tag'])) {
            throw new RuntimeException("Invalid payload structure.");
        }

        $iv = base64_decode($data['iv'], true);
        $ciphertext = base64_decode($data['value'], true);
        $tag = base64_decode($data['tag'], true);

        $decrypted = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new RuntimeException("The payload MAC or tag is invalid.");
        }

        return ($data['s'] ?? $unserialize) ? unserialize($decrypted) : $decrypted;
    }
}

