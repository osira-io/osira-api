<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Incident\Incident;
use App\Entity\Notification\NotificationChannel;
use App\Entity\Notification\NotificationEvent;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class EmailNotificationSender
{
    public function __construct(private MailerInterface $mailer, private string $fromAddress, private string $frontendBaseUrl)
    {
    }

    public function send(NotificationChannel $channel, NotificationEvent $event, Incident $incident, string $observedValue, \DateTimeImmutable $occurredAt): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'Osira'))
            ->to(...$channel->emailRecipients())
            ->subject(\sprintf('[Osira] %s — %s', mb_strtoupper($incident->severity()->value), $incident->title()))
            ->htmlTemplate('emails/notification/incident.html.twig')
            ->textTemplate('emails/notification/incident.txt.twig')
            ->context([
                'event' => $event->value,
                'incident' => $incident,
                'node' => $incident->node(),
                'alertRule' => $incident->alertRule(),
                'observedValue' => $observedValue,
                'occurredAt' => $occurredAt,
                'frontendUrl' => $this->frontendUrl($incident),
            ]);
        $this->mailer->send($email);
    }

    private function frontendUrl(Incident $incident): ?string
    {
        $baseUrl = rtrim(trim($this->frontendBaseUrl), '/');

        return '' === $baseUrl ? null : $baseUrl.'/incidents/'.(string) $incident->id();
    }
}
