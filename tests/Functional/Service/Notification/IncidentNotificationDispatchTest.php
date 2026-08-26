<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Notification;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Message\Notification\IncidentTransitionNotification;
use App\Service\Alert\AlertEvaluationResult;
use App\Service\Alert\AlertEvaluationStatus;
use App\Service\Incident\IncidentManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class IncidentNotificationDispatchTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($entityManager);
        $schema->dropDatabase();
        $schema->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->transport()->reset();
    }

    public function testOnlyOpenedAndResolvedTransitionsAreDispatched(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $node = new Node('notify-node', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.cpu', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 95', null, true, $now);
        $rule = new AlertRule('CPU high', 'CPU usage is high', $item, AlertOperator::GT, '90', '80', 300, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$node, $item, $rule] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $manager = self::getContainer()->get(IncidentManager::class);
        $manager->apply($node, $rule, new AlertEvaluationResult(AlertEvaluationStatus::FIRING, '95', [], $now));
        self::assertCount(1, $this->transport()->getSent());
        $opened = $this->transport()->getSent()[0]->getMessage();
        self::assertInstanceOf(IncidentTransitionNotification::class, $opened);
        self::assertSame('incident.firing', $opened->event);

        $manager->apply($node, $rule, new AlertEvaluationResult(AlertEvaluationStatus::FIRING, '96', [], $now->modify('+1 minute')));
        self::assertCount(1, $this->transport()->getSent());

        $manager->apply($node, $rule, new AlertEvaluationResult(AlertEvaluationStatus::OK, '79', [], $now->modify('+2 minutes')));
        self::assertCount(2, $this->transport()->getSent());
        $resolved = $this->transport()->getSent()[1]->getMessage();
        self::assertInstanceOf(IncidentTransitionNotification::class, $resolved);
        self::assertSame('incident.resolved', $resolved->event);

        $manager->apply($node, $rule, new AlertEvaluationResult(AlertEvaluationStatus::OK, '78', [], $now->modify('+3 minutes')));
        self::assertCount(2, $this->transport()->getSent());
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async_notifications');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
