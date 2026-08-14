<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\NodeGroup\Presentation\Api\Provider\NodeGroupCollectionProvider;
use App\Rbac\Infrastructure\Security\PermissionCode;
use App\Shared\Application\Pagination\PaginationParameters;
use App\Shared\Presentation\Api\Resource\PaginationMetadata;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'NodeGroupCollection',
    operations: [
        new Get(
            uriTemplate: '/node-groups',
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Page number, starting at 1.',
                    constraints: [new Assert\Positive()],
                    castToNativeType: true,
                ),
                'itemsPerPage' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE],
                    description: 'Number of node groups returned per page.',
                    constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)],
                    castToNativeType: true,
                ),
            ],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::NODE_GROUPS_READ."')",
            provider: NodeGroupCollectionProvider::class,
            openapi: new OpenApiOperation(
                tags: ['NodeGroup'],
                summary: 'Lists node groups with pagination metadata.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final readonly class NodeGroupCollectionOutput
{
    /** @param list<NodeGroupOutput> $items */
    public function __construct(
        public array $items,
        public PaginationMetadata $metadata,
    ) {
    }
}
