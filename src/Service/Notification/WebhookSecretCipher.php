<?php

declare(strict_types=1);

namespace App\Service\Notification;

final readonly class WebhookSecretCipher
{
    private string $key;

    public function __construct(string $applicationSecret)
    {
        $this->key = sodium_crypto_generichash($applicationSecret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $secret): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return sodium_bin2base64($nonce.sodium_crypto_secretbox($secret, $nonce, $this->key), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    public function decrypt(string $ciphertext): string
    {
        try {
            $decoded = sodium_base642bin($ciphertext, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $secret = sodium_crypto_secretbox_open($encrypted, $nonce, $this->key);
        } catch (\SodiumException) {
            throw new \RuntimeException('Webhook secret cannot be decrypted.');
        }
        if (false === $secret) {
            throw new \RuntimeException('Webhook secret cannot be decrypted.');
        }

        return $secret;
    }
}
