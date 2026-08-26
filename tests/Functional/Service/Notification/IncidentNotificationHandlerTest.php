<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Notification;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Incident\Incident;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationChannelType;
use App\Entity\Notification\NotificationDeliveryStatus;
use App\Entity\Notification\NotificationEvent;
use App\Entity\Notification\NotificationRule;
use App\Message\Notification\IncidentTransitionNotification;
use App\MessageHandler\Notification\IncidentTransitionNotificationHandler;
use App\Repository\Notification\NotificationDeliveryRepository;
use App\Service\Notification\IncidentNotificationPayloadFactory;
use App\Service\Notification\WebhookNotificationSender;
use App\Service\Notification\WebhookSecretCipher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IncidentNotificationHandlerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($entityManager);
        $schema->dropDatabase();
        $schema->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testRoutingDisabledFiltersTransitionsAndIdempotence(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $group = new NodeGroup('Production', null, $now);
        $node = new Node('notify-node', null, 'linux', 'amd64', $now, $now);
        $node->replaceGroups([$group]);
        $item = new ItemDefinition('custom.cpu', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 95', null, true, $now);
        $alertRule = new AlertRule('CPU high', 'CPU usage is high', $item, AlertOperator::GT, '90', '80', 300, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);
        $incident = new Incident($node, $alertRule, AlertSeverity::CRITICAL, 'CPU high', 'CPU usage is high', [], 'identity', '95', $now, $now, $now);

        $groupChannel = $this->emailChannel('Group channel', true, $now);
        $nodeChannel = $this->emailChannel('Node channel', true, $now);
        $disabledChannel = $this->emailChannel('Disabled channel', false, $now);
        $severityChannel = $this->emailChannel('Severity mismatch', true, $now);
        $disabledRuleChannel = $this->emailChannel('Disabled rule', true, $now);

        $groupRule = new NotificationRule('Group routing', true, [AlertSeverity::CRITICAL], $now);
        $groupRule->replaceChannels([$groupChannel, $disabledChannel], $now);
        $groupRule->replaceNodeGroups([$group], $now);
        $nodeRule = new NotificationRule('Node routing', true, [AlertSeverity::CRITICAL], $now);
        $nodeRule->replaceChannels([$nodeChannel], $now);
        $nodeRule->replaceNodes([$node], $now);
        $severityRule = new NotificationRule('Warning only', true, [AlertSeverity::WARNING], $now);
        $severityRule->replaceChannels([$severityChannel], $now);
        $disabledRule = new NotificationRule('Disabled', false, [AlertSeverity::CRITICAL], $now);
        $disabledRule->replaceChannels([$disabledRuleChannel], $now);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$group, $node, $item, $alertRule, $incident, $groupChannel, $nodeChannel, $disabledChannel, $severityChannel, $disabledRuleChannel, $groupRule, $nodeRule, $severityRule, $disabledRule] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $message = new IncidentTransitionNotification((string) $incident->id(), 'incident.firing', '95', $now->format(\DATE_ATOM));
        $handler = self::getContainer()->get(IncidentTransitionNotificationHandler::class);
        $handler($message);

        $repository = self::getContainer()->get(NotificationDeliveryRepository::class);
        $openedDeliveries = $repository->findAll();
        self::assertCount(2, $openedDeliveries);
        self::assertNotSame((string) $openedDeliveries[0]->id(), (string) $openedDeliveries[1]->id(), 'Different deliveries must expose different delivery IDs.');
        foreach ($openedDeliveries as $delivery) {
            self::assertSame(NotificationDeliveryStatus::SENT, $delivery->status());
        }

        $handler($message);
        self::assertSame(2, $repository->count([]), 'A Messenger redelivery must not resend already-sent channel deliveries.');

        $incident->resolve('79', $now->modify('+1 minute'));
        $entityManager->flush();
        $handler(new IncidentTransitionNotification((string) $incident->id(), 'incident.resolved', '79', $now->modify('+1 minute')->format(\DATE_ATOM)));
        self::assertSame(4, $repository->count([]), 'Resolved is a distinct business transition.');
    }

    public function testFailedWebhookRemainsPendingAndMessengerRetryCanCompleteItOnce(): void
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $node = new Node('retry-node', null, 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.retry', 'Retry', null, null, ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $alertRule = new AlertRule('Retry alert', 'Retry alert', $item, AlertOperator::GT, '0', null, 60, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);
        $incident = new Incident($node, $alertRule, AlertSeverity::CRITICAL, 'Retry alert', 'Retry alert', [], 'retry-identity', '1', $now, $now, $now);
        $channel = new NotificationChannel('Retry webhook', NotificationChannelType::WEBHOOK, true, [], 'https://hooks.example.test/retry', null, $now);
        $rule = new NotificationRule('Retry routing', true, [AlertSeverity::CRITICAL], $now);
        $rule->replaceChannels([$channel], $now);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$node, $item, $alertRule, $incident, $channel, $rule] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $attemptDeliveryIds = [];
        $attemptHeaderIds = [];
        $sender = new WebhookNotificationSender(
            new MockHttpClient(static function (string $method, string $url, array $options) use (&$attemptDeliveryIds, &$attemptHeaderIds): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://hooks.example.test/retry', $url);
                $body = $options['body'] ?? null;
                self::assertIsString($body);
                $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
                self::assertIsArray($payload);
                $deliveryId = $payload['deliveryId'] ?? null;
                self::assertIsString($deliveryId);
                $attemptDeliveryIds[] = $deliveryId;
                $headers = $options['normalized_headers'] ?? null;
                self::assertIsArray($headers);
                $deliveryHeaders = $headers['osira-delivery-id'] ?? null;
                self::assertIsArray($deliveryHeaders);
                $deliveryHeader = $deliveryHeaders[0] ?? null;
                self::assertIsString($deliveryHeader);
                $attemptHeaderIds[] = substr($deliveryHeader, \strlen('Osira-Delivery-Id: '));

                return 1 === \count($attemptDeliveryIds)
                    ? new MockResponse('temporary failure', ['http_code' => 503])
                    : new MockResponse('', ['http_code' => 204]);
            }),
            new WebhookSecretCipher('test-app-secret'),
            new IncidentNotificationPayloadFactory(),
            1.0,
        );
        self::getContainer()->set(WebhookNotificationSender::class, $sender);
        $handler = self::getContainer()->get(IncidentTransitionNotificationHandler::class);
        $message = new IncidentTransitionNotification((string) $incident->id(), NotificationEvent::INCIDENT_OPENED->value, '1', $now->format(\DATE_ATOM));
        try {
            $handler($message);
            self::fail('The first delivery must fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Webhook delivery failed.', $exception->getMessage());
        }

        $repository = self::getContainer()->get(NotificationDeliveryRepository::class);
        self::assertCount(1, $repository->findAll());
        $delivery = $repository->findAll()[0];
        self::assertSame(NotificationDeliveryStatus::PENDING, $delivery->status());
        self::assertSame((string) $delivery->id(), $attemptDeliveryIds[0]);
        self::assertSame($attemptDeliveryIds[0], $attemptHeaderIds[0]);

        $handler($message);
        self::assertSame(1, $repository->count([]));
        self::assertSame(NotificationDeliveryStatus::SENT, $repository->findAll()[0]->status());
        self::assertSame([$attemptDeliveryIds[0], $attemptDeliveryIds[0]], $attemptDeliveryIds, 'A Messenger retry must preserve the delivery ID.');
        self::assertSame($attemptDeliveryIds, $attemptHeaderIds, 'Webhook headers and payloads must use the same delivery ID.');
    }

    private function emailChannel(string $name, bool $enabled, \DateTimeImmutable $now): NotificationChannel
    {
        return new NotificationChannel($name, NotificationChannelType::EMAIL, $enabled, [mb_strtolower(str_replace(' ', '.', $name)).'@example.com'], null, null, $now);
    }
}
