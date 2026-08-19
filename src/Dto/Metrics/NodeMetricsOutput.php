<?php

declare(strict_types=1);

namespace App\Dto\Metrics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\Rbac\PermissionCode;
use App\State\Provider\Metrics\NodeMetricsProvider;

#[ApiResource(
    shortName: 'Metric',
    operations: [
        new Get(
            uriTemplate: '/nodes/{id}/metrics',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::METRICS_READ."')",
            provider: NodeMetricsProvider::class,
            openapi: new OpenApiOperation(tags: ['Metric'], summary: 'Reads the current effective Osira metrics for one node.', security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class NodeMetricsOutput
{
    /** @param list<MetricSampleOutput> $samples */
    public function __construct(
        public string $nodeId,
        public array $samples,
    ) {
    }
}
