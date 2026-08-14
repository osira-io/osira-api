<?php

declare(strict_types=1);

namespace App\Rbac\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Rbac\Application\Service\RoleManager;
use App\Rbac\Presentation\Api\Dto\UpdateRoleInput;
use App\Rbac\Presentation\Api\Factory\RoleOutputFactory;
use App\Rbac\Presentation\Api\Resource\RoleOutput;

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
