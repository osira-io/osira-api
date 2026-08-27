<?php

declare(strict_types=1);

namespace App\Dto\Agent;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

final class AgentMetricBatchInput
{
    /** @var list<array{itemKey: string, value: mixed, collectedAt?: string|null}> */
    #[Assert\Count(min: 1, max: 500)]
    #[Assert\All([new Assert\Collection(
        fields: [
            'itemKey' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(max: 128)]),
            'value' => new Assert\Required([new Assert\NotNull()]),
            'collectedAt' => new Assert\Optional([new Assert\Type('string'), new Assert\Regex(
                pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D',
                message: 'collectedAt must be an RFC3339 datetime with an explicit timezone offset.',
            )]),
        ],
        allowExtraFields: false,
    )])]
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'minItems' => 1,
        'maxItems' => 500,
        'items' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['itemKey', 'value'],
            'properties' => [
                'itemKey' => ['type' => 'string', 'maxLength' => 128],
                'value' => ['oneOf' => [['type' => 'number'], ['type' => 'integer'], ['type' => 'boolean']]],
                'collectedAt' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            ],
        ],
    ])]
    public array $samples = [];
}
