<?php

declare(strict_types=1);

namespace App\State\Provider\Monitoring;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Monitoring\MonitoringTemplateCollectionOutput;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Service\Monitoring\MonitoringTemplateOutputFactory;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<MonitoringTemplateCollectionOutput> */
final readonly class MonitoringTemplateCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MonitoringTemplateRepository $repository,
        private PaginatorInterface $paginator,
        private MonitoringTemplateOutputFactory $outputFactory,
        private PaginationMetadataFactory $metadataFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MonitoringTemplateCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate(
            $this->repository->createOrderedQueryBuilder(),
            $parameters->page,
            $parameters->itemsPerPage,
        );

        $items = [];
        foreach ($pagination->getItems() as $monitoringTemplate) {
            if (!$monitoringTemplate instanceof MonitoringTemplate) {
                throw new \LogicException('Unexpected monitoring template pagination result.');
            }
            $items[] = $this->outputFactory->create($monitoringTemplate);
        }

        return new MonitoringTemplateCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
