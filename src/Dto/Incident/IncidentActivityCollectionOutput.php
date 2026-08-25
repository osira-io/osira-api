<?php

declare(strict_types=1);

namespace App\Dto\Incident;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Shared\PaginationMetadata;
use App\Security\Rbac\PermissionCode;
use App\Service\Shared\PaginationParameters;
use App\State\Provider\Incident\IncidentActivityCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'IncidentActivity',
    operations: [
        new Get(
            uriTemplate: '/incidents/{id}/activities',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            parameters: [
                'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], constraints: [new Assert\Positive()], castToNativeType: true),
                'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE], constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)], castToNativeType: true),
            ],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::INCIDENTS_READ."')",
            provider: IncidentActivityCollectionProvider::class,
            openapi: new OpenApiOperation(
                tags: ['Incident'],
                summary: 'Lists the stable oldest-first incident business timeline.',
                description: 'Opened and resolved entries are reconstructed from the automatic incident lifecycle; acknowledgements and comments are persisted product activities.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final readonly class IncidentActivityCollectionOutput
{
    /** @param list<IncidentActivityOutput> $items */
    public function __construct(public array $items, public PaginationMetadata $metadata)
    {
    }
}
