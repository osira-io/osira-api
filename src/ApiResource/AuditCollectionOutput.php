<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Audit\AuditEntityCatalog;
use App\Security\PermissionCode;
use App\State\AuditCollectionProvider;
use App\State\PaginationParameters;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'AuditCollection', operations: [
    new Get(
        uriTemplate: '/audits',
        parameters: [
            'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], constraints: [new Assert\Positive()], castToNativeType: true),
            'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE], constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)], castToNativeType: true),
            'entity' => new QueryParameter(schema: ['type' => 'string', 'enum' => AuditEntityCatalog::NAMES], description: 'Audited entity short name.', constraints: [new Assert\Choice(choices: AuditEntityCatalog::NAMES)]),
            'entityId' => new QueryParameter(schema: ['type' => 'string'], description: 'Audited entity identifier.', constraints: [new Assert\Length(max: 255)]),
            'action' => new QueryParameter(schema: ['type' => 'string', 'enum' => ['insert', 'update', 'remove', 'associate', 'dissociate']], description: 'Audit transaction type.', constraints: [new Assert\Choice(choices: ['insert', 'update', 'remove', 'associate', 'dissociate'])]),
            'actor' => new QueryParameter(schema: ['type' => 'string'], description: 'Actor user ULID or CLI command identifier.', constraints: [new Assert\Length(max: 255)]),
            'dateFrom' => new QueryParameter(schema: ['type' => 'string', 'format' => 'date-time'], description: 'Inclusive ISO 8601 lower date bound.'),
            'dateTo' => new QueryParameter(schema: ['type' => 'string', 'format' => 'date-time'], description: 'Inclusive ISO 8601 upper date bound.'),
        ],
        strictQueryParameterValidation: true,
        security: "is_granted('".PermissionCode::AUDIT_LOGS_READ."')",
        provider: AuditCollectionProvider::class,
        openapi: new OpenApiOperation(tags: ['Audit'], summary: 'Lists control-plane audit events with filters and pagination.', security: [['JWT' => []]]),
    ),
])]
final readonly class AuditCollectionOutput
{
    /** @param list<AuditOutput> $items */
    public function __construct(public array $items, public PaginationMetadata $metadata)
    {
    }
}
