<?php

declare(strict_types=1);

namespace App\State\Provider\Maintenance;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Maintenance\MaintenanceWindowCollectionOutput;
use App\Entity\Maintenance\MaintenanceWindow;
use App\Repository\Maintenance\MaintenanceWindowRepository;
use App\Service\Maintenance\MaintenanceWindowOutputFactory;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<MaintenanceWindowCollectionOutput> */
final readonly class MaintenanceWindowCollectionProvider implements ProviderInterface
{
    public function __construct(
        private MaintenanceWindowRepository $repository,
        private PaginatorInterface $paginator,
        private MaintenanceWindowOutputFactory $outputFactory,
        private PaginationMetadataFactory $metadataFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MaintenanceWindowCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate(
            $this->repository->createOrderedQueryBuilder(),
            $parameters->page,
            $parameters->itemsPerPage,
        );

        $items = [];
        foreach ($pagination->getItems() as $window) {
            if (!$window instanceof MaintenanceWindow) {
                throw new \LogicException('Unexpected maintenance window pagination result.');
            }
            $items[] = $this->outputFactory->create($window);
        }

        return new MaintenanceWindowCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
