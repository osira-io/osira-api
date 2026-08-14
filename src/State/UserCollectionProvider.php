<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\UserCollectionOutput;
use App\Entity\User;
use App\Repository\UserRepository;
use Knp\Component\Pager\PaginatorInterface;

/** @implements ProviderInterface<UserCollectionOutput> */
final readonly class UserCollectionProvider implements ProviderInterface
{
    public function __construct(private UserRepository $repository, private PaginatorInterface $paginator, private UserOutputFactory $outputFactory, private PaginationMetadataFactory $metadataFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserCollectionOutput
    {
        $parameters = PaginationParameters::fromOperation($operation);
        $pagination = $this->paginator->paginate($this->repository->createOrderedQueryBuilder(), $parameters->page, $parameters->itemsPerPage);
        $items = [];
        foreach ($pagination->getItems() as $user) {
            if (!$user instanceof User) {
                throw new \LogicException('Unexpected user pagination result.');
            }
            $items[] = $this->outputFactory->create($user);
        }

        return new UserCollectionOutput($items, $this->metadataFactory->create($pagination));
    }
}
