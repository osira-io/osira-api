<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Shared\PaginationMetadata;
use App\Security\Rbac\PermissionCode;
use App\Service\Shared\PaginationParameters;
use App\State\Provider\Monitoring\ItemDefinitionCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'ItemDefinitionCollection',
    operations: [
        new Get(
            uriTemplate: '/item-definitions',
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Page number, starting at 1.',
                    constraints: [new Assert\Positive()],
                    castToNativeType: true,
                ),
                'itemsPerPage' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE],
                    description: 'Number of item definitions returned per page.',
                    constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)],
                    castToNativeType: true,
                ),
            ],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::ITEM_DEFINITIONS_READ."')",
            provider: ItemDefinitionCollectionProvider::class,
            openapi: new OpenApiOperation(
                tags: ['ItemDefinition'],
                summary: 'Lists item definitions with pagination metadata.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final readonly class ItemDefinitionCollectionOutput
{
    /** @param list<ItemDefinitionOutput> $items */
    public function __construct(
        public array $items,
        public PaginationMetadata $metadata,
    ) {
    }
}
