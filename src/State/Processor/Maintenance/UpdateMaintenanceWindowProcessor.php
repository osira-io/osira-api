<?php

declare(strict_types=1);

namespace App\State\Processor\Maintenance;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Maintenance\MaintenanceWindowOutput;
use App\Dto\Maintenance\UpdateMaintenanceWindowInput;
use App\Service\Maintenance\MaintenanceWindowManager;
use App\Service\Maintenance\MaintenanceWindowOutputFactory;

/** @implements ProcessorInterface<UpdateMaintenanceWindowInput, MaintenanceWindowOutput> */
final readonly class UpdateMaintenanceWindowProcessor implements ProcessorInterface
{
    public function __construct(private MaintenanceWindowManager $manager, private MaintenanceWindowOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenanceWindowOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
