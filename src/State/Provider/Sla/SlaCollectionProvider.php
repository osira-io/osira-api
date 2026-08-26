<?php

declare(strict_types=1);

namespace App\State\Provider\Sla;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Sla\SlaCollectionOutput;
use App\Entity\Sla\Sla;
use App\Repository\Sla\SlaRepository;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use App\Service\Sla\SlaOutputFactory;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<SlaCollectionOutput> */
final readonly class SlaCollectionProvider implements ProviderInterface
{
    public function __construct(private SlaRepository $repository, private PaginatorInterface $paginator, private SlaOutputFactory $factory, private PaginationMetadataFactory $metadataFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SlaCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate($this->repository->createOrderedQueryBuilder(), $parameters->page, $parameters->itemsPerPage);
        $items = [];
        foreach ($pagination->getItems() as $sla) {
            if (!$sla instanceof Sla) {
                throw new \LogicException('Unexpected SLA pagination result.');
            }
            $items[] = $this->factory->create($sla);
        }

        return new SlaCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
