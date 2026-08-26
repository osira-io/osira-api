<?php

declare(strict_types=1);

namespace App\State\Processor\Sla;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Sla\CreateSlaInput;
use App\Dto\Sla\SlaOutput;
use App\Service\Sla\SlaManager;
use App\Service\Sla\SlaOutputFactory;

/** @implements ProcessorInterface<CreateSlaInput, SlaOutput> */
final readonly class CreateSlaProcessor implements ProcessorInterface
{
    public function __construct(private SlaManager $manager, private SlaOutputFactory $factory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SlaOutput
    {
        return $this->factory->create($this->manager->create($data));
    }
}
