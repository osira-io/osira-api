<?php

declare(strict_types=1);

namespace App\State\Provider\Rbac;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Rbac\PermissionOutput;
use App\Entity\Rbac\Permission;
use App\Repository\Rbac\PermissionRepository;
use App\Service\Rbac\PermissionOutputFactory;
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
