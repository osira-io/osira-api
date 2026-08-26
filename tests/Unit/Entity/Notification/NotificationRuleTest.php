<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Notification;

use App\Entity\Alert\AlertSeverity;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationChannelType;
use App\Entity\Notification\NotificationRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotificationRuleTest extends TestCase
{
    #[Test]
    public function itFiltersByStateSeverityNodeAndGroup(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $production = new NodeGroup('Production', null, $now);
        $productionNode = new Node('prod-01', null, 'linux', 'amd64', $now, $now);
        $productionNode->replaceGroups([$production]);
        $otherNode = new Node('dev-01', null, 'linux', 'amd64', $now, $now);
        $channel = new NotificationChannel('Infra', NotificationChannelType::EMAIL, true, ['infra@example.com'], null, null, $now);
        $rule = new NotificationRule('Critical production', true, [AlertSeverity::CRITICAL], $now);
        $rule->replaceChannels([$channel], $now);
        $rule->replaceNodeGroups([$production], $now);

        self::assertTrue($rule->matches($productionNode, AlertSeverity::CRITICAL));
        self::assertFalse($rule->matches($productionNode, AlertSeverity::WARNING));
        self::assertFalse($rule->matches($otherNode, AlertSeverity::CRITICAL));

        $rule->replaceNodes([$otherNode], $now);
        self::assertTrue($rule->matches($otherNode, AlertSeverity::CRITICAL));

        $rule->update('Critical production', false, [AlertSeverity::CRITICAL], $now);
        self::assertFalse($rule->matches($productionNode, AlertSeverity::CRITICAL));
    }
}
