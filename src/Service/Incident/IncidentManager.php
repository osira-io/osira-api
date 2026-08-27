<?php

declare(strict_types=1);

namespace App\Service\Incident;

use App\Entity\Alert\AlertRule;
use App\Entity\Incident\Incident;
use App\Entity\Node\Node;
use App\Factory\Incident\IncidentFactory;
use App\Message\Notification\IncidentTransitionNotification;
use App\Repository\Incident\IncidentRepository;
use App\Service\Alert\AlertEvaluationResult;
use App\Service\Alert\AlertEvaluationStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class IncidentManager
{
    public function __construct(
        private IncidentRepository $incidents,
        private IncidentFactory $factory,
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function apply(Node $node, AlertRule $rule, AlertEvaluationResult $result): ?Incident
    {
        if (!\in_array($result->status, [AlertEvaluationStatus::FIRING, AlertEvaluationStatus::OK], true)) {
            return null;
        }

        $identity = IncidentFactory::identity($node, $rule, $result->labels);

        return $this->connection->transactional(function () use ($node, $rule, $result, $identity): ?Incident {
            $this->lockIdentity($identity);
            $incident = $this->incidents->findActiveByIdentity($identity);

            // Agent retries re-import the same VictoriaMetrics point. Do not count an
            // evaluation at the same (or an older) collection time twice.
            if ($incident instanceof Incident && $result->evaluatedAt <= $incident->lastTriggeredAt()) {
                return $incident;
            }

            if (AlertEvaluationStatus::FIRING === $result->status) {
                $value = $result->observedValue ?? '';
                if ($incident instanceof Incident) {
                    $incident->recordTrigger($value, $result->evaluatedAt);
                } else {
                    $incident = $this->factory->create($node, $rule, $result->labels, $value, $result->evaluatedAt);
                    $this->entityManager->persist($incident);
                    $this->entityManager->flush();
                    $this->messageBus->dispatch(new IncidentTransitionNotification(
                        (string) $incident->id(),
                        'incident.firing',
                        $value,
                        $result->evaluatedAt->format(\DATE_ATOM),
                    ));
                }
                $this->entityManager->flush();

                return $incident;
            }

            if ($incident instanceof Incident) {
                $incident->resolve($result->observedValue ?? $incident->lastValue(), $result->evaluatedAt);
                $this->entityManager->flush();
                $this->messageBus->dispatch(new IncidentTransitionNotification(
                    (string) $incident->id(),
                    'incident.resolved',
                    $result->observedValue ?? $incident->lastValue(),
                    $result->evaluatedAt->format(\DATE_ATOM),
                ));
            }

            return $incident;
        });
    }

    private function lockIdentity(string $identity): void
    {
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:identity, 0))', ['identity' => $identity]);
        }
    }
}
