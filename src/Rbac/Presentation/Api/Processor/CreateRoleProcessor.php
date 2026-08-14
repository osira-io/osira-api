<?php

declare(strict_types=1);

namespace App\Rbac\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Rbac\Application\Service\RoleManager;
use App\Rbac\Presentation\Api\Dto\CreateRoleInput;
use App\Rbac\Presentation\Api\Factory\RoleOutputFactory;
use App\Rbac\Presentation\Api\Resource\RoleOutput;

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
