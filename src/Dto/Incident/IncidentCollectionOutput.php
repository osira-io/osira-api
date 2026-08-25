<?php

declare(strict_types=1);

namespace App\Dto\Incident;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Shared\PaginationMetadata;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Incident\IncidentStatus;
use App\Security\Rbac\PermissionCode;
use App\Service\Shared\PaginationParameters;
use App\State\Provider\Incident\IncidentCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'IncidentCollection',
    operations: [
        new Get(
            uriTemplate: '/incidents',
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], constraints: [new Assert\Positive()], castToNativeType: true),
                'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE], constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)], castToNativeType: true),
                'status' => new QueryParameter(schema: ['type' => 'string', 'enum' => [IncidentStatus::FIRING->value, IncidentStatus::RESOLVED->value]], description: 'Exact incident lifecycle status.'),
                'severity' => new QueryParameter(schema: ['type' => 'string', 'enum' => [AlertSeverity::INFO->value, AlertSeverity::WARNING->value, AlertSeverity::CRITICAL->value]], description: 'Exact severity snapshot.'),
                'node' => new QueryParameter(schema: ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'], description: 'Node ULID.', constraints: [new Assert\Ulid()]),
                'alertRule' => new QueryParameter(schema: ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'], description: 'Alert rule ULID.', constraints: [new Assert\Ulid()]),
                'date' => new QueryParameter(schema: ['type' => 'string', 'format' => 'date-time'], description: 'Only incidents first triggered at or after this ISO 8601 timestamp.', constraints: [new Assert\DateTime(format: \DateTimeInterface::ATOM)]),
            ],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::INCIDENTS_READ."')",
            provider: IncidentCollectionProvider::class,
            openapi: new OpenApiOperation(tags: ['Incident'], summary: 'Lists server-generated incidents with filters.', security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class IncidentCollectionOutput
{
    /** @param list<IncidentOutput> $items */
    public function __construct(public array $items, public PaginationMetadata $metadata)
    {
    }
}
