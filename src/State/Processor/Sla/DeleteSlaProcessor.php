<?php

declare(strict_types=1);

namespace App\State\Processor\Sla;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\Sla\SlaManager;

/** @implements ProcessorInterface<object, void> */
final readonly class DeleteSlaProcessor implements ProcessorInterface
{
    public function __construct(private SlaManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? '';
        $this->manager->delete(\is_string($id) ? $id : '');
    }
}
