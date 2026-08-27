<?php

declare(strict_types=1);

namespace App\Dto\Agent;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\Processor\Agent\AgentMetricIngestionProcessor;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'AgentMetricIngestion',
    operations: [
        new Post(
            uriTemplate: '/agent/metrics',
            status: Response::HTTP_OK,
            security: "is_granted('ROLE_AGENT')",
            input: AgentMetricBatchInput::class,
            output: self::class,
            read: false,
            processor: AgentMetricIngestionProcessor::class,
            denormalizationContext: ['allow_extra_attributes' => false],
            openapi: new OpenApiOperation(
                tags: ['AgentMetricIngestion'],
                summary: 'Ingests an atomic batch of metrics for the authenticated agent Node.',
                description: 'Accepts 1 to 500 samples identified by the stable ItemDefinition key. The Node is always derived from the AgentCredential. The complete batch is rejected when any sample is invalid.',
                security: [['AgentBearer' => []]],
            ),
        ),
    ],
)]
final readonly class AgentMetricIngestionOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $accepted,
    ) {
    }
}
