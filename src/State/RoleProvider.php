<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\RoleOutput;
use App\Entity\Role;
use App\Repository\RoleRepository;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<RoleOutput> */
final readonly class RoleProvider implements ProviderInterface
{
    public function __construct(private RoleRepository $repository, private RoleOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?RoleOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $role = $this->repository->find(new Ulid($id));

        return $role instanceof Role ? $this->outputFactory->create($role) : null;
    }
}
