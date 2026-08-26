<?php

declare(strict_types=1);

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Notification\NotificationChannelCollectionOutput;
use App\Entity\Notification\NotificationChannel;
use App\Repository\Notification\NotificationChannelRepository;
use App\Service\Notification\NotificationChannelOutputFactory;
use App\Service\Shared\PaginationMetadataFactory;
use App\Service\Shared\PaginationParameters;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<NotificationChannelCollectionOutput> */
final readonly class NotificationChannelCollectionProvider implements ProviderInterface
{
    public function __construct(private NotificationChannelRepository $repository, private PaginatorInterface $paginator, private NotificationChannelOutputFactory $outputFactory, private PaginationMetadataFactory $metadataFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationChannelCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate($this->repository->createOrderedQueryBuilder(), $parameters->page, $parameters->itemsPerPage);
        $items = [];
        foreach ($pagination->getItems() as $channel) {
            if (!$channel instanceof NotificationChannel) {
                throw new \LogicException('Unexpected notification channel pagination result.');
            }
            $items[] = $this->outputFactory->create($channel);
        }

        return new NotificationChannelCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
