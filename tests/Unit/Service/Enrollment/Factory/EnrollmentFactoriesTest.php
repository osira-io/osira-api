<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Enrollment\Factory;

use App\Entity\Node\Node;
use App\Service\Enrollment\Factory\AgentCredentialFactory;
use App\Service\Enrollment\Factory\AgentFactory;
use App\Service\Enrollment\Factory\EnrollmentTokenFactory;
use App\Service\Shared\Exception\ResourceValidationException;
use PHPUnit\Framework\TestCase;

final class EnrollmentFactoriesTest extends TestCase
{
    public function testAgentFactoryCreatesAgentAndLinksItToNode(): void
    {
        $node = new Node('srv-01', null, 'linux', 'x86_64', new \DateTimeImmutable('2026-08-19T12:00:00+00:00'), new \DateTimeImmutable('2026-08-19T12:00:00+00:00'));
        $factory = new AgentFactory();
        $installedAt = new \DateTimeImmutable('2026-08-19T12:05:00+00:00');

        $agent = $factory->create($node, ' 0.1.0 ', $installedAt, $installedAt);

        self::assertSame($node, $agent->node());
        self::assertSame('0.1.0', $agent->version());
        self::assertSame($installedAt, $agent->installedAt());
        self::assertSame($installedAt, $agent->createdAt());
    }

    public function testAgentCredentialFactoryRejectsBlankHash(): void
    {
        $node = new Node('srv-01', null, 'linux', 'x86_64', new \DateTimeImmutable('2026-08-19T12:00:00+00:00'), new \DateTimeImmutable('2026-08-19T12:00:00+00:00'));
        $agent = new AgentFactory()->create($node, '0.1.0', new \DateTimeImmutable('2026-08-19T12:05:00+00:00'));
        $factory = new AgentCredentialFactory();

        $this->expectException(ResourceValidationException::class);
        $factory->create($agent, '   ', new \DateTimeImmutable('2026-08-19T12:06:00+00:00'), null);
    }

    public function testEnrollmentTokenFactoryRejectsNonIncreasingExpiration(): void
    {
        $factory = new EnrollmentTokenFactory();
        $createdAt = new \DateTimeImmutable('2026-08-19T12:00:00+00:00');

        $this->expectException(ResourceValidationException::class);
        $factory->create('hash', $createdAt, $createdAt);
    }
}
