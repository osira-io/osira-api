<?php

declare(strict_types=1);

namespace App\State\Processor\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\Monitoring\MonitoringTemplateManager;

/** @implements ProcessorInterface<mixed, void> */
final readonly class DeleteMonitoringTemplateProcessor implements ProcessorInterface
{
    public function __construct(private MonitoringTemplateManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? '';
        $this->manager->delete(\is_string($id) ? $id : '');
    }
}
