<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PermissionOutput;
use App\Entity\Permission;
use App\Repository\PermissionRepository;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<PermissionOutput> */
final readonly class PermissionProvider implements ProviderInterface
{
    public function __construct(private PermissionRepository $repository, private PermissionOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?PermissionOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $permission = $this->repository->find(new Ulid($id));

        return $permission instanceof Permission ? $this->outputFactory->create($permission) : null;
    }
}
