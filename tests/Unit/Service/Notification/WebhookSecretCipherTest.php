<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\Service\Notification\WebhookSecretCipher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WebhookSecretCipherTest extends TestCase
{
    #[Test]
    public function itEncryptsAuthenticatesAndNeverEmbedsPlaintext(): void
    {
        $cipher = new WebhookSecretCipher('test-application-secret');
        $encrypted = $cipher->encrypt('webhook-secret-value');

        self::assertNotSame('webhook-secret-value', $encrypted);
        self::assertStringNotContainsString('webhook-secret-value', $encrypted);
        self::assertSame('webhook-secret-value', $cipher->decrypt($encrypted));

        $this->expectException(\RuntimeException::class);
        $cipher->decrypt(substr($encrypted, 0, -2).'xx');
    }
}
