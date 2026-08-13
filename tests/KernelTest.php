<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Kernel::class)]
final class KernelTest extends TestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        $kernel = new Kernel('test', true);

        try {
            $kernel->boot();

            self::assertSame('test', $kernel->getEnvironment());
            self::assertTrue($kernel->isDebug());
            self::assertSame($kernel, $kernel->getContainer()->get('kernel'));
        } finally {
            $kernel->shutdown();
        }
    }
}
