<?php

declare(strict_types=1);

namespace App\State\Processor\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Monitoring\MonitoringTemplateOutput;
use App\Dto\Monitoring\UpdateMonitoringTemplateInput;
use App\Service\Monitoring\MonitoringTemplateManager;
use App\Service\Monitoring\MonitoringTemplateOutputFactory;

/** @implements ProcessorInterface<UpdateMonitoringTemplateInput, MonitoringTemplateOutput> */
final readonly class UpdateMonitoringTemplateProcessor implements ProcessorInterface
{
    public function __construct(
        private MonitoringTemplateManager $manager,
        private MonitoringTemplateOutputFactory $outputFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MonitoringTemplateOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
