<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Service\UserManager;
use App\User\Presentation\Api\Dto\UpdateUserInput;
use App\User\Presentation\Api\Factory\UserOutputFactory;
use App\User\Presentation\Api\Resource\UserOutput;

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
