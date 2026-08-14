<?php

declare(strict_types=1);

namespace App\State\Processor\Rbac;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Rbac\RoleOutput;
use App\Dto\Rbac\UpdateRoleInput;
use App\Service\Rbac\RoleManager;
use App\Service\Rbac\RoleOutputFactory;

/** @implements ProcessorInterface<UpdateRoleInput, RoleOutput> */
final readonly class UpdateRoleProcessor implements ProcessorInterface
{
    public function __construct(private RoleManager $manager, private RoleOutputFactory $factory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RoleOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->factory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
