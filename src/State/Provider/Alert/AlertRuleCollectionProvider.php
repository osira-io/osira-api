<?php

declare(strict_types=1);

namespace App\State\Provider\Alert;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Alert\AlertRuleCollectionOutput;
use App\Entity\Alert\AlertRule;
use App\Repository\Alert\AlertRuleRepository;
use App\Service\Alert\AlertRuleOutputFactory;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<AlertRuleCollectionOutput> */
final readonly class AlertRuleCollectionProvider implements ProviderInterface
{
    public function __construct(
        private AlertRuleRepository $repository,
        private PaginatorInterface $paginator,
        private AlertRuleOutputFactory $outputFactory,
        private PaginationMetadataFactory $metadataFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AlertRuleCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate(
            $this->repository->createOrderedQueryBuilder(),
            $parameters->page,
            $parameters->itemsPerPage,
        );

        $items = [];
        foreach ($pagination->getItems() as $alertRule) {
            if (!$alertRule instanceof AlertRule) {
                throw new \LogicException('Unexpected alert rule pagination result.');
            }
            $items[] = $this->outputFactory->create($alertRule);
        }

        return new AlertRuleCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
