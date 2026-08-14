<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Service\UserManager;
use App\User\Presentation\Api\Dto\CreateUserInput;
use App\User\Presentation\Api\Factory\UserOutputFactory;
use App\User\Presentation\Api\Resource\UserOutput;

/** @implements ProcessorInterface<CreateUserInput, UserOutput> */
final readonly class CreateUserProcessor implements ProcessorInterface
{
    public function __construct(private UserManager $manager, private UserOutputFactory $factory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserOutput
    {
        return $this->factory->create($this->manager->create($data));
    }
}
