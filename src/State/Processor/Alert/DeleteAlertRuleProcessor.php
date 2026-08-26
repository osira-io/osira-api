<?php

declare(strict_types=1);

namespace App\State\Processor\Alert;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Service\Alert\AlertRuleManager;

/** @implements ProcessorInterface<mixed, void> */
final readonly class DeleteAlertRuleProcessor implements ProcessorInterface
{
    public function __construct(private AlertRuleManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $id = $uriVariables['id'] ?? '';
        $this->manager->delete(\is_string($id) ? $id : '');
    }
}
