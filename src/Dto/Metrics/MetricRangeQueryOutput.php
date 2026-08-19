<?php

declare(strict_types=1);

namespace App\Dto\Metrics;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\Rbac\PermissionCode;
use App\State\Provider\Metrics\MetricRangeQueryProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Metric',
    operations: [
        new Get(
            uriTemplate: '/metrics/query-range',
            parameters: [
                'nodeId' => new QueryParameter(schema: ['type' => 'string'], constraints: [new Assert\Ulid()]),
                'itemKey' => new QueryParameter(schema: ['type' => 'string'], constraints: [new Assert\NotBlank(), new Assert\Length(max: 128)]),
                'from' => new QueryParameter(schema: ['type' => 'string'], constraints: [new Assert\NotBlank()]),
                'to' => new QueryParameter(schema: ['type' => 'string'], constraints: [new Assert\NotBlank()]),
                'stepSeconds' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1], constraints: [new Assert\Positive()], castToNativeType: true),
                'device' => new QueryParameter(schema: ['type' => 'string'], required: false, constraints: [new Assert\Length(max: 128), new Assert\Regex('/^[A-Za-z0-9._:-]+$/')]),
                'interface' => new QueryParameter(schema: ['type' => 'string'], required: false, constraints: [new Assert\Length(max: 128), new Assert\Regex('/^[A-Za-z0-9._:-]+$/')]),
                'container' => new QueryParameter(schema: ['type' => 'string'], required: false, constraints: [new Assert\Length(max: 128), new Assert\Regex('/^[A-Za-z0-9._:-]+$/')]),
            ],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::METRICS_READ."')",
            provider: MetricRangeQueryProvider::class,
            openapi: new OpenApiOperation(tags: ['Metric'], summary: 'Reads one Osira metric series over a time range for one node.', security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class MetricRangeQueryOutput
{
    /** @param list<MetricSeriesOutput> $series */
    public function __construct(
        public string $nodeId,
        public string $itemKey,
        public array $series,
    ) {
    }
}
