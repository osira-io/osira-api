<?php

declare(strict_types=1);

namespace App\State\Processor\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Monitoring\CreateMonitoringTemplateInput;
use App\Dto\Monitoring\MonitoringTemplateOutput;
use App\Service\Monitoring\MonitoringTemplateManager;
use App\Service\Monitoring\MonitoringTemplateOutputFactory;

/** @implements ProcessorInterface<CreateMonitoringTemplateInput, MonitoringTemplateOutput> */
final readonly class CreateMonitoringTemplateProcessor implements ProcessorInterface
{
    public function __construct(
        private MonitoringTemplateManager $manager,
        private MonitoringTemplateOutputFactory $outputFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MonitoringTemplateOutput
    {
        return $this->outputFactory->create($this->manager->create($data));
    }
}
