<?php

declare(strict_types=1);

namespace App\State\Provider\Alert;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Alert\AlertRuleOutput;
use App\Entity\Alert\AlertRule;
use App\Repository\Alert\AlertRuleRepository;
use App\Service\Alert\AlertRuleOutputFactory;
use Symfony\Component\Uid\Ulid;

/** @implements ProviderInterface<AlertRuleOutput> */
final readonly class AlertRuleProvider implements ProviderInterface
{
    public function __construct(
        private AlertRuleRepository $repository,
        private AlertRuleOutputFactory $outputFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AlertRuleOutput
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Ulid::isValid($id)) {
            return null;
        }
        $alertRule = $this->repository->findWithItemDefinition(new Ulid($id));

        return $alertRule instanceof AlertRule ? $this->outputFactory->create($alertRule) : null;
    }
}
