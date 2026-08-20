<?php

declare(strict_types=1);

namespace App\Dto\Agent;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\Provider\Agent\AgentConfigProvider;

#[ApiResource(
    shortName: 'AgentConfig',
    operations: [
        new Get(
            uriTemplate: '/agent/config',
            security: "is_granted('ROLE_AGENT')",
            provider: AgentConfigProvider::class,
            openapi: new OpenApiOperation(
                tags: ['AgentConfig'],
                summary: 'Returns the authenticated agent effective control-plane configuration.',
                security: [['AgentBearer' => []]],
            ),
        ),
    ],
)]
final readonly class AgentConfigOutput
{
    /** @param list<AgentConfigItemOutput> $items */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $version,
        public \DateTimeImmutable $generatedAt,
        public AgentConfigNodeOutput $node,
        public AgentConfigAgentOutput $agent,
        public array $items,
    ) {
    }
}
