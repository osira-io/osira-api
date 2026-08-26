<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Incident\Incident;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Node\Node;
use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationChannelType;
use App\Entity\Notification\NotificationEvent;
use App\Service\Notification\EmailNotificationSender;
use App\Service\Notification\IncidentNotificationPayloadFactory;
use App\Service\Notification\WebhookNotificationSender;
use App\Service\Notification\WebhookSecretCipher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Uid\Ulid;

final class NotificationTransportTest extends TestCase
{
    #[Test]
    public function emailSupportsMultipleRecipientsAndProfessionalIncidentContext(): void
    {
        $mailer = new class implements MailerInterface {
            /** @var list<RawMessage> */ public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->messages[] = $message;
            }
        };
        $incident = $this->incident();
        $channel = new NotificationChannel('Infra email', NotificationChannelType::EMAIL, true, ['one@example.com', 'two@example.com'], null, null, new \DateTimeImmutable());
        $sender = new EmailNotificationSender($mailer, 'notifications@osira.test', 'https://app.osira.test');

        $sender->send($channel, NotificationEvent::INCIDENT_OPENED, $incident, '95', new \DateTimeImmutable('2026-08-25T12:00:00+00:00'));

        self::assertCount(1, $mailer->messages);
        $email = $mailer->messages[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertCount(2, $email->getTo());
        $subject = $email->getSubject();
        self::assertIsString($subject);
        self::assertStringContainsString('CRITICAL', $subject);
        self::assertSame('https://app.osira.test/incidents/'.(string) $incident->id(), $email->getContext()['frontendUrl'] ?? null);
        self::assertSame('95', $email->getContext()['observedValue'] ?? null);
    }

    #[Test]
    public function webhookPayloadIsStableAndSignedWithoutExposingEntities(): void
    {
        $deliveryId = (string) new Ulid();
        $requestCount = 0;
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requestCount, $deliveryId): MockResponse {
            ++$requestCount;
            self::assertSame('POST', $method);
            self::assertSame('https://hooks.example.test/incidents', $url);
            $body = $options['body'] ?? null;
            self::assertIsString($body);
            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertSame($deliveryId, $payload['deliveryId'] ?? null);
            self::assertSame('incident.firing', $payload['event'] ?? null);
            $incidentPayload = $payload['incident'] ?? null;
            self::assertIsArray($incidentPayload);
            self::assertSame('firing', $incidentPayload['status'] ?? null);
            $nodePayload = $incidentPayload['node'] ?? null;
            self::assertIsArray($nodePayload);
            self::assertSame('notify-node', $nodePayload['hostname'] ?? null);
            self::assertArrayNotHasKey('webhookSecret', $payload);
            $normalizedHeaders = $options['normalized_headers'] ?? null;
            self::assertIsArray($normalizedHeaders);
            $signatureHeaders = $normalizedHeaders['osira-signature'] ?? null;
            self::assertIsArray($signatureHeaders);
            self::assertSame('Osira-Signature: sha256='.hash_hmac('sha256', $body, 'signing-secret-value'), $signatureHeaders[0] ?? null);
            self::assertSame(['Osira-Delivery-Id: '.$deliveryId], $normalizedHeaders['osira-delivery-id'] ?? null);
            self::assertSame(['Idempotency-Key: '.$deliveryId], $normalizedHeaders['idempotency-key'] ?? null);
            self::assertSame(1.5, $options['timeout'] ?? null);

            return new MockResponse('', ['http_code' => 204]);
        });
        $cipher = new WebhookSecretCipher('test-app-secret');
        $channel = new NotificationChannel('Webhook', NotificationChannelType::WEBHOOK, true, [], 'https://hooks.example.test/incidents', $cipher->encrypt('signing-secret-value'), new \DateTimeImmutable());
        $sender = new WebhookNotificationSender($httpClient, $cipher, new IncidentNotificationPayloadFactory(), 1.5);

        $sender->send($channel, NotificationEvent::INCIDENT_OPENED, $this->incident(), '95', new \DateTimeImmutable('2026-08-25T12:00:00+00:00'), $deliveryId);
        self::assertSame(1, $requestCount);
    }

    #[Test]
    public function webhookTimeoutAndHttpFailureAreRetryableFailures(): void
    {
        $channel = new NotificationChannel('Webhook', NotificationChannelType::WEBHOOK, true, [], 'https://hooks.example.test/incidents', null, new \DateTimeImmutable());
        $incident = $this->incident();
        $cipher = new WebhookSecretCipher('test-app-secret');

        $httpFailure = new WebhookNotificationSender(new MockHttpClient(new MockResponse('failure', ['http_code' => 503])), $cipher, new IncidentNotificationPayloadFactory(), 1.0);
        try {
            $httpFailure->send($channel, NotificationEvent::INCIDENT_OPENED, $incident, '95', new \DateTimeImmutable(), (string) new Ulid());
            self::fail('An HTTP failure must throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Webhook delivery failed.', $exception->getMessage());
        }

        $timeout = new WebhookNotificationSender(new MockHttpClient(static function (): never { throw new TransportException('timed out with sensitive transport details'); }), $cipher, new IncidentNotificationPayloadFactory(), 1.0);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Webhook delivery failed.');
        $timeout->send($channel, NotificationEvent::INCIDENT_OPENED, $incident, '95', new \DateTimeImmutable(), (string) new Ulid());
    }

    private function incident(): Incident
    {
        $now = new \DateTimeImmutable('2026-08-25T12:00:00+00:00');
        $node = new Node('notify-node', 'Production API', 'linux', 'amd64', $now, $now);
        $item = new ItemDefinition('custom.cpu', 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 95', null, true, $now);
        $rule = new AlertRule('CPU high', 'CPU usage is high', $item, AlertOperator::GT, '90', '80', 300, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);

        return new Incident($node, $rule, AlertSeverity::CRITICAL, 'CPU high', 'CPU usage is high', [], 'identity', '95', $now, $now, $now);
    }
}
