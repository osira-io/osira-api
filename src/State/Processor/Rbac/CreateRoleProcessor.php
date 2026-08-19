<?php

declare(strict_types=1);

namespace App\State\Processor\Rbac;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Rbac\CreateRoleInput;
use App\Dto\Rbac\RoleOutput;
use App\Service\Rbac\RoleManager;
use App\Service\Rbac\RoleOutputFactory;

/** @implements ProcessorInterface<CreateRoleInput, RoleOutput> */
final readonly class CreateRoleProcessor implements ProcessorInterface
{
    public function __construct(private RoleManager $manager, private RoleOutputFactory $factory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RoleOutput
    {
        return $this->factory->create($this->manager->create($data));
    }
}
