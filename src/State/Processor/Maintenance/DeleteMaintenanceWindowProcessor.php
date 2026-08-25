<?php

declare(strict_types=1);

namespace App\State\Processor\Maintenance;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\Maintenance\MaintenanceWindowManager;

/** @implements ProcessorInterface<object, void> */
final readonly class DeleteMaintenanceWindowProcessor implements ProcessorInterface
{
    public function __construct(private MaintenanceWindowManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? '';
        $this->manager->delete(\is_string($id) ? $id : '');
    }
}
