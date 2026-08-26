<?php

declare(strict_types=1);

namespace App\State\Provider\Sla;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Sla\SlaOutput;
use App\Service\Sla\SlaManager;
use App\Service\Sla\SlaOutputFactory;

/** @implements ProviderInterface<SlaOutput> */
final readonly class SlaProvider implements ProviderInterface
{
    public function __construct(private SlaManager $manager, private SlaOutputFactory $factory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SlaOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->factory->create($this->manager->find(\is_string($id) ? $id : ''));
    }
}
