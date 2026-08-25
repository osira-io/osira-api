<?php

declare(strict_types=1);

namespace App\State\Provider\Incident;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Incident\IncidentOutput;
use App\Entity\Incident\Incident;
use App\Repository\Incident\IncidentRepository;
use App\Service\Incident\IncidentOutputFactory;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<IncidentOutput> */
final readonly class IncidentProvider implements ProviderInterface
{
    public function __construct(private IncidentRepository $repository, private IncidentOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?IncidentOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $incident = $this->repository->findWithRelations(new Ulid($id));

        return $incident instanceof Incident ? $this->outputFactory->create($incident) : null;
    }
}
