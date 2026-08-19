<?php

declare(strict_types=1);

namespace App\State\Provider\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Monitoring\MonitoringTemplateOutput;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Service\Monitoring\MonitoringTemplateOutputFactory;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<MonitoringTemplateOutput> */
final readonly class MonitoringTemplateProvider implements ProviderInterface
{
    public function __construct(
        private MonitoringTemplateRepository $repository,
        private MonitoringTemplateOutputFactory $outputFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?MonitoringTemplateOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $monitoringTemplate = $this->repository->find(new Ulid($id));

        return $monitoringTemplate instanceof MonitoringTemplate ? $this->outputFactory->create($monitoringTemplate) : null;
    }
}
