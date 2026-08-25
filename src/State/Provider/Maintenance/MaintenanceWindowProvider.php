<?php

declare(strict_types=1);

namespace App\State\Provider\Maintenance;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Maintenance\MaintenanceWindowOutput;
use App\Entity\Maintenance\MaintenanceWindow;
use App\Repository\Maintenance\MaintenanceWindowRepository;
use App\Service\Maintenance\MaintenanceWindowOutputFactory;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<MaintenanceWindowOutput> */
final readonly class MaintenanceWindowProvider implements ProviderInterface
{
    public function __construct(private MaintenanceWindowRepository $repository, private MaintenanceWindowOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?MaintenanceWindowOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $window = $this->repository->find(new Ulid($id));

        return $window instanceof MaintenanceWindow ? $this->outputFactory->create($window) : null;
    }
}
