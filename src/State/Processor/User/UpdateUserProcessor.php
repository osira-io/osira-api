<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\User\UpdateUserInput;
use App\Dto\User\UserOutput;
use App\Service\User\UserManager;
use App\Service\User\UserOutputFactory;

/** @implements ProcessorInterface<UpdateUserInput, UserOutput> */
final readonly class UpdateUserProcessor implements ProcessorInterface
{
    public function __construct(private UserManager $manager, private UserOutputFactory $factory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->factory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
