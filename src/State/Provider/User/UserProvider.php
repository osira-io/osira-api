<?php

declare(strict_types=1);

namespace App\State\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\User\UserOutput;
use App\Entity\User\User;
use App\Repository\User\UserRepository;
use App\Service\User\UserOutputFactory;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<UserOutput> */
final readonly class UserProvider implements ProviderInterface
{
    public function __construct(private UserRepository $repository, private UserOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?UserOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $user = $this->repository->find(new Ulid($id));

        return $user instanceof User ? $this->outputFactory->create($user) : null;
    }
}
