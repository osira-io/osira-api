<?php

declare(strict_types=1);

namespace App\MessageHandler\Notification;

use App\Entity\Incident\Incident;
use App\Entity\Notification\NotificationChannelType;
use App\Entity\Notification\NotificationDelivery;
use App\Entity\Notification\NotificationDeliveryStatus;
use App\Entity\Notification\NotificationEvent;
use App\Message\Notification\IncidentTransitionNotification;
use App\Repository\Incident\IncidentRepository;
use App\Repository\Notification\NotificationDeliveryRepository;
use App\Repository\Notification\NotificationRuleRepository;
use App\Service\Notification\EmailNotificationSender;
use App\Service\Notification\WebhookNotificationSender;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final readonly class IncidentTransitionNotificationHandler
{
    public function __construct(
        private IncidentRepository $incidents,
        private NotificationRuleRepository $rules,
        private NotificationDeliveryRepository $deliveries,
        private EmailNotificationSender $emailSender,
        private WebhookNotificationSender $webhookSender,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(IncidentTransitionNotification $message): void
    {
        if (!Ulid::isValid($message->incidentId)) {
            throw new UnrecoverableMessageHandlingException('Notification incident does not exist.');
        }
        $incident = $this->incidents->find(new Ulid($message->incidentId));
        if (!$incident instanceof Incident) {
            throw new UnrecoverableMessageHandlingException('Notification incident does not exist.');
        }
        try {
            $event = NotificationEvent::from($message->event);
            $occurredAt = new \DateTimeImmutable($message->occurredAt);
        } catch (\Throwable) {
            throw new UnrecoverableMessageHandlingException('Notification message is invalid.');
        }

        $channels = [];
        foreach ($this->rules->findEnabled() as $rule) {
            if (!$rule->matches($incident->node(), $incident->severity())) {
                continue;
            }
            foreach ($rule->channels() as $channel) {
                if ($channel->isEnabled()) {
                    $channels[(string) $channel->id()] = $channel;
                }
            }
        }

        foreach ($channels as $channel) {
            $delivery = $this->deliveries->findForEvent($incident, $event, $channel);
            if ($delivery instanceof NotificationDelivery && NotificationDeliveryStatus::SENT === $delivery->status()) {
                continue;
            }
            if (!$delivery instanceof NotificationDelivery) {
                $delivery = new NotificationDelivery($incident, $channel, $event, $this->clock->now());
                $this->entityManager->persist($delivery);
                $this->entityManager->flush();
            }

            match ($channel->type()) {
                NotificationChannelType::EMAIL => $this->emailSender->send($channel, $event, $incident, $message->observedValue, $occurredAt),
                NotificationChannelType::WEBHOOK => $this->webhookSender->send($channel, $event, $incident, $message->observedValue, $occurredAt, (string) $delivery->id()),
            };
            $delivery->markSent($this->clock->now());
            $this->entityManager->flush();
        }
    }
}
