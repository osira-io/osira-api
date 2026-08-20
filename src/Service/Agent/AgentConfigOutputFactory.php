<?php

declare(strict_types=1);

namespace App\Service\Agent;

use App\Dto\Agent\AgentConfigAgentOutput;
use App\Dto\Agent\AgentConfigItemOutput;
use App\Dto\Agent\AgentConfigNodeOutput;
use App\Dto\Agent\AgentConfigOutput;
use App\Entity\Monitoring\ItemDefinition;
use App\Security\Agent\AuthenticatedAgent;
use App\Service\Monitoring\EffectiveNodeMonitoringResolver;
use Psr\Clock\ClockInterface;

final readonly class AgentConfigOutputFactory
{
    public function __construct(
        private EffectiveNodeMonitoringResolver $effectiveNodeMonitoringResolver,
        private AgentConfigVersionHasher $versionHasher,
        private ClockInterface $clock,
    ) {
    }

    public function create(AuthenticatedAgent $authenticatedAgent): AgentConfigOutput
    {
        $node = $authenticatedAgent->node();
        $agent = $authenticatedAgent->agent();
        $effectiveMonitoring = $this->effectiveNodeMonitoringResolver->resolve($node);

        $items = array_map(
            static fn (ItemDefinition $item): AgentConfigItemOutput => new AgentConfigItemOutput(
                $item->key(),
                $item->valueType()->value,
                $item->unit(),
                $item->intervalSeconds(),
                $item->timeoutSeconds(),
                [],
            ),
            $effectiveMonitoring->items,
        );

        $version = $this->versionHasher->hash([
            'agent' => [
                'id' => (string) $agent->id(),
                'version' => $agent->version(),
            ],
            'items' => array_map(static fn (AgentConfigItemOutput $item): array => [
                'intervalSeconds' => $item->intervalSeconds,
                'key' => $item->key,
                'parameters' => $item->parameters,
                'timeoutSeconds' => $item->timeoutSeconds,
                'unit' => $item->unit,
                'valueType' => $item->valueType,
            ], $items),
            'node' => [
                'hostname' => $node->hostname(),
                'id' => (string) $node->id(),
            ],
        ]);

        return new AgentConfigOutput(
            $version,
            $this->clock->now(),
            new AgentConfigNodeOutput((string) $node->id(), $node->hostname()),
            new AgentConfigAgentOutput((string) $agent->id(), $agent->version()),
            $items,
        );
    }
}
