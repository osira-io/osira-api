<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Incident\Incident;
use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationEvent;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class WebhookNotificationSender
{
    public function __construct(private HttpClientInterface $httpClient, private WebhookSecretCipher $cipher, private IncidentNotificationPayloadFactory $payloadFactory, private float $timeoutSeconds)
    {
    }

    public function send(NotificationChannel $channel, NotificationEvent $event, Incident $incident, string $observedValue, \DateTimeImmutable $occurredAt, string $deliveryId): void
    {
        $url = $channel->webhookUrl();
        if (null === $url) {
            throw new \RuntimeException('Webhook delivery failed.');
        }
        $body = json_encode($this->payloadFactory->create($event, $incident, $observedValue, $occurredAt, $deliveryId), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type' => 'application/json',
            'Osira-Event' => $event->value,
            'Osira-Delivery-Id' => $deliveryId,
            'Idempotency-Key' => $deliveryId,
        ];
        if (null !== $channel->webhookSecretCiphertext()) {
            $headers['Osira-Signature'] = 'sha256='.hash_hmac('sha256', $body, $this->cipher->decrypt($channel->webhookSecretCiphertext()));
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => $this->timeoutSeconds,
                'max_duration' => $this->timeoutSeconds,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface) {
            throw new \RuntimeException('Webhook delivery failed.');
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Webhook delivery failed.');
        }
    }
}
