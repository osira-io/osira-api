<?php

declare(strict_types=1);

namespace App\Dto\Metrics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\Rbac\PermissionCode;
use App\State\Provider\Metrics\MetricInstantQueryProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Metric',
    operations: [
        new Get(
            uriTemplate: '/metrics/query',
            parameters: [
                'nodeId' => new QueryParameter(schema: ['type' => 'string'], constraints: [new Assert\Ulid()]),
                'itemKey' => new QueryParameter(schema: ['type' => 'string'], constraints: [new Assert\NotBlank(), new Assert\Length(max: 128)]),
                'device' => new QueryParameter(schema: ['type' => 'string'], required: false, constraints: [new Assert\Length(max: 128), new Assert\Regex('/^[A-Za-z0-9._:-]+$/')]),
                'interface' => new QueryParameter(schema: ['type' => 'string'], required: false, constraints: [new Assert\Length(max: 128), new Assert\Regex('/^[A-Za-z0-9._:-]+$/')]),
                'container' => new QueryParameter(schema: ['type' => 'string'], required: false, constraints: [new Assert\Length(max: 128), new Assert\Regex('/^[A-Za-z0-9._:-]+$/')]),
            ],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::METRICS_READ."')",
            provider: MetricInstantQueryProvider::class,
            openapi: new OpenApiOperation(tags: ['Metric'], summary: 'Reads the current value of one Osira metric for one node.', security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class MetricInstantQueryOutput
{
    /** @param list<MetricSampleOutput> $samples */
    public function __construct(
        public string $nodeId,
        public string $itemKey,
        public array $samples,
    ) {
    }
}
