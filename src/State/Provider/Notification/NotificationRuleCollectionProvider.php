<?php

declare(strict_types=1);

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Notification\NotificationRuleCollectionOutput;
use App\Entity\Notification\NotificationRule;
use App\Repository\Notification\NotificationRuleRepository;
use App\Service\Notification\NotificationRuleOutputFactory;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<NotificationRuleCollectionOutput> */
final readonly class NotificationRuleCollectionProvider implements ProviderInterface
{
    public function __construct(private NotificationRuleRepository $repository, private PaginatorInterface $paginator, private NotificationRuleOutputFactory $outputFactory, private PaginationMetadataFactory $metadataFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationRuleCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate($this->repository->createOrderedQueryBuilder(), $parameters->page, $parameters->itemsPerPage);
        $items = [];
        foreach ($pagination->getItems() as $rule) {
            if (!$rule instanceof NotificationRule) {
                throw new \LogicException('Unexpected notification rule pagination result.');
            }
            $items[] = $this->outputFactory->create($rule);
        }

        return new NotificationRuleCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
