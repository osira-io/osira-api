<?php

declare(strict_types=1);

namespace App\State\Processor\Maintenance;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Maintenance\CreateMaintenanceWindowInput;
use App\Dto\Maintenance\MaintenanceWindowOutput;
use App\Service\Maintenance\MaintenanceWindowManager;
use App\Service\Maintenance\MaintenanceWindowOutputFactory;

/** @implements ProcessorInterface<CreateMaintenanceWindowInput, MaintenanceWindowOutput> */
final readonly class CreateMaintenanceWindowProcessor implements ProcessorInterface
{
    public function __construct(private MaintenanceWindowManager $manager, private MaintenanceWindowOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenanceWindowOutput
    {
        return $this->outputFactory->create($this->manager->create($data));
    }
}
