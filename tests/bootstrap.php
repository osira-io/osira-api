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

(static function (): void {
    $projectDirectory = dirname(__DIR__);
    $databaseTestUrl = $_ENV['DATABASE_TEST_URL'] ?? null;
    $sharedSqliteUrls = [
        'sqlite:///%kernel.project_dir%/var/test.db',
        'sqlite:///'.$projectDirectory.'/var/test.db',
    ];

    if (!is_string($databaseTestUrl) || !in_array($databaseTestUrl, $sharedSqliteUrls, true)) {
        return;
    }

    $databasePath = sprintf('%s/var/test-%d.db', $projectDirectory, getmypid());
    $databaseUrl = 'sqlite:///'.$databasePath;
    $_ENV['DATABASE_TEST_URL'] = $databaseUrl;
    $_SERVER['DATABASE_TEST_URL'] = $databaseUrl;
    putenv('DATABASE_TEST_URL='.$databaseUrl);

    register_shutdown_function(static function () use ($databasePath): void {
        foreach ([$databasePath, $databasePath.'-journal', $databasePath.'-shm', $databasePath.'-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    });
})();
