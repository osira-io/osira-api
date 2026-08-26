<?php

declare(strict_types=1);

namespace App\State\Processor\Alert;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Alert\AlertRuleOutput;
use App\Dto\Alert\CreateAlertRuleInput;
use App\Service\Alert\AlertRuleManager;
use App\Service\Alert\AlertRuleOutputFactory;

/** @implements ProcessorInterface<CreateAlertRuleInput, AlertRuleOutput> */
final readonly class CreateAlertRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private AlertRuleManager $manager,
        private AlertRuleOutputFactory $outputFactory,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AlertRuleOutput
    {
        return $this->outputFactory->create($this->manager->create($data));
    }
}
