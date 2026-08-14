<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Application\Pagination\PaginationMetadataFactory;
use App\Shared\Application\Pagination\PaginationParameters;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use App\User\Presentation\Api\Factory\UserOutputFactory;
use App\User\Presentation\Api\Resource\UserCollectionOutput;
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
