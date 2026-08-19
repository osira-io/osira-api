<?php

declare(strict_types=1);

namespace App\State\Processor\Rbac;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\Rbac\RoleManager;

/** @implements ProcessorInterface<object, void> */
final readonly class DeleteRoleProcessor implements ProcessorInterface
{
    public function __construct(private RoleManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? '';
        $this->manager->delete(\is_string($id) ? $id : '');
    }
}
