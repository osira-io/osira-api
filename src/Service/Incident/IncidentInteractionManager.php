<?php

declare(strict_types=1);

namespace App\Service\Incident;

use App\Entity\Incident\Incident;
use App\Entity\Incident\IncidentActivity;
use App\Entity\Incident\IncidentStatus;
use App\Entity\User\User;
use App\Factory\Incident\IncidentActivityFactory;
use App\Repository\Incident\IncidentRepository;
use App\Service\Shared\Exception\ResourceConflictException;
use App\Service\Shared\Exception\ResourceNotFoundException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class IncidentInteractionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IncidentRepository $incidents,
        private Connection $connection,
        private IncidentActivityFactory $factory,
        private ClockInterface $clock,
    ) {
    }

    public function acknowledge(string $id, User $actor, ?string $message): Incident
    {
        return $this->connection->transactional(function () use ($id, $actor, $message): Incident {
            $incident = $this->findIncident($id, true);
            if (IncidentStatus::FIRING !== $incident->status()) {
                throw new ResourceConflictException('Only a firing incident can be acknowledged.');
            }
            if (null !== $incident->acknowledgedAt()) {
                throw new ResourceConflictException('The incident is already acknowledged.');
            }

            $now = $this->clock->now();
            $incident->acknowledge($actor, $now);
            $this->entityManager->persist($this->factory->acknowledged($incident, $actor, $message, $now));
            $this->entityManager->flush();

            return $incident;
        });
    }

    public function comment(string $id, User $actor, string $message): IncidentActivity
    {
        $incident = $this->findIncident($id, false);
        $activity = $this->factory->comment($incident, $actor, $message, $this->clock->now());
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    private function findIncident(string $id, bool $forUpdate): Incident
    {
        if (!Ulid::isValid($id)) {
            throw new ResourceNotFoundException('Incident not found.');
        }
        $lockMode = $forUpdate && $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform ? LockMode::PESSIMISTIC_WRITE : null;
        $incident = $this->incidents->findForInteraction(new Ulid($id), $lockMode);
        if (!$incident instanceof Incident) {
            throw new ResourceNotFoundException('Incident not found.');
        }

        return $incident;
    }
}
