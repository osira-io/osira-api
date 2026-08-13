<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(static function (): void {
    $jwtDirectory = dirname(__DIR__).'/var/jwt';
    $privateKeyPath = $jwtDirectory.'/test-private.pem';
    $publicKeyPath = $jwtDirectory.'/test-public.pem';
    if (is_file($privateKeyPath) && is_file($publicKeyPath)) {
        return;
    }

    if (!is_dir($jwtDirectory) && !mkdir($jwtDirectory, 0700, true) && !is_dir($jwtDirectory)) {
        throw new RuntimeException('Unable to create the test JWT directory.');
    }

    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
    if (false === $key || !openssl_pkey_export($key, $privateKey, 'osira-test-passphrase')) {
        throw new RuntimeException('Unable to generate the test JWT private key.');
    }

    $details = openssl_pkey_get_details($key);
    if (false === $details
        || false === file_put_contents($privateKeyPath, $privateKey, \LOCK_EX)
        || false === file_put_contents($publicKeyPath, $details['key'], \LOCK_EX)
    ) {
        throw new RuntimeException('Unable to write the test JWT key pair.');
    }

    chmod($privateKeyPath, 0600);
})();

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
