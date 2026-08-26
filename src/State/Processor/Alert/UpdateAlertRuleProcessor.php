<?php

declare(strict_types=1);

namespace App\State\Processor\Alert;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Alert\AlertRuleOutput;
use App\Dto\Alert\UpdateAlertRuleInput;
use App\Service\Alert\AlertRuleManager;
use App\Service\Alert\AlertRuleOutputFactory;

/** @implements ProcessorInterface<UpdateAlertRuleInput, AlertRuleOutput> */
final readonly class UpdateAlertRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private AlertRuleManager $manager,
        private AlertRuleOutputFactory $outputFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AlertRuleOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
