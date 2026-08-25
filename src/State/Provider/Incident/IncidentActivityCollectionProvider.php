<?php

declare(strict_types=1);

namespace App\State\Provider\Incident;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Incident\IncidentActivityCollectionOutput;
use App\Dto\Incident\IncidentActivityOutput;
use App\Dto\Shared\PaginationMetadata;
use App\Entity\Incident\Incident;
use App\Repository\Incident\IncidentActivityRepository;
use App\Repository\Incident\IncidentRepository;
use App\Service\Incident\IncidentActivityOutputFactory;
use App\Service\Shared\Exception\ResourceNotFoundException;
use App\Service\Shared\PaginationParameters;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<IncidentActivityCollectionOutput> */
final readonly class IncidentActivityCollectionProvider implements ProviderInterface
{
    public function __construct(
        private IncidentRepository $incidents,
        private IncidentActivityRepository $activities,
        private IncidentActivityOutputFactory $outputFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): IncidentActivityCollectionOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            throw new ResourceNotFoundException('Incident not found.');
        }
        $incident = $this->incidents->findWithRelations(new Ulid($id));
        if (!$incident instanceof Incident) {
            throw new ResourceNotFoundException('Incident not found.');
        }

        $parameters = PaginationParameters::fromOperation($operation);
        $offset = ($parameters->page - 1) * $parameters->itemsPerPage;
        $persistentStart = max(0, $offset - 2);
        $items = array_map(
            $this->outputFactory->create(...),
            $this->activities->findTimelineSlice($incident, $persistentStart, $parameters->itemsPerPage + 4),
        );
        $items[] = new IncidentActivityOutput((string) $incident->id().'-opened', 'opened', $incident->message(), null, $incident->firstTriggeredAt());
        if (null !== $incident->resolvedAt()) {
            $items[] = new IncidentActivityOutput((string) $incident->id().'-resolved', 'resolved', null, null, $incident->resolvedAt());
        }
        usort($items, self::compare(...));
        $items = \array_slice($items, $offset - $persistentStart, $parameters->itemsPerPage);

        $totalItems = $this->activities->countForIncident($incident) + 1 + (null === $incident->resolvedAt() ? 0 : 1);
        $totalPages = 0 === $totalItems ? 0 : (int) ceil($totalItems / $parameters->itemsPerPage);

        return new IncidentActivityCollectionOutput($items, new PaginationMetadata(
            $parameters->page,
            $parameters->itemsPerPage,
            $totalItems,
            $totalPages,
            $parameters->page > 1,
            $parameters->page < $totalPages,
        ));
    }

    private static function compare(IncidentActivityOutput $left, IncidentActivityOutput $right): int
    {
        $dateOrder = $left->createdAt <=> $right->createdAt;
        if (0 !== $dateOrder) {
            return $dateOrder;
        }
        $rank = ['opened' => 0, 'acknowledged' => 1, 'comment' => 2, 'resolved' => 3];

        return [$rank[$left->type], $left->id] <=> [$rank[$right->type], $right->id];
    }
}
