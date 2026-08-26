<?php

declare(strict_types=1);

namespace App\Dto\Sla;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Shared\PaginationMetadata;
use App\Security\Rbac\PermissionCode;
use App\Service\Shared\PaginationParameters;
use App\State\Provider\Sla\SlaCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Sla',
    operations: [
        new GetCollection(
            uriTemplate: '/slas',
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Page number, starting at 1.',
                    constraints: [new Assert\Positive()],
                    castToNativeType: true,
                ),
                'itemsPerPage' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE],
                    description: 'Number of SLA configurations returned per page.',
                    constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)],
                    castToNativeType: true,
                ),
            ],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::SLAS_READ."')",
            provider: SlaCollectionProvider::class,
            openapi: new OpenApiOperation(tags: ['Sla'], summary: 'Lists SLA configurations with pagination metadata.', security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class SlaCollectionOutput
{
    /** @param list<SlaOutput> $items */
    public function __construct(
        public array $items,
        public PaginationMetadata $metadata,
    ) {
    }
}
