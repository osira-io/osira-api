<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\UserOutput;
use App\Entity\User;
use App\Repository\UserRepository;
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
