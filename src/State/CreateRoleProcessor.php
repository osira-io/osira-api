<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\RoleOutput;
use App\Application\Role\RoleManager;
use App\Dto\CreateRoleInput;

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
