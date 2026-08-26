<?php

declare(strict_types=1);

namespace App\State\Processor\Sla;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Sla\SlaOutput;
use App\Dto\Sla\UpdateSlaInput;
use App\Service\Sla\SlaManager;
use App\Service\Sla\SlaOutputFactory;

/** @implements ProcessorInterface<UpdateSlaInput, SlaOutput> */
final readonly class UpdateSlaProcessor implements ProcessorInterface
{
    public function __construct(private SlaManager $manager, private SlaOutputFactory $factory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SlaOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->factory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
