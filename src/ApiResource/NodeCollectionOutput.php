<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\NodeCollectionProvider;
use App\State\PaginationParameters;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'NodeCollection',
    operations: [
        new Get(
            uriTemplate: '/nodes',
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Page number, starting at 1.',
                    constraints: [new Assert\Positive()],
                    castToNativeType: true,
                ),
                'itemsPerPage' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE],
                    description: 'Number of nodes returned per page.',
                    constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)],
                    castToNativeType: true,
                ),
            ],
            strictQueryParameterValidation: true,
            provider: NodeCollectionProvider::class,
            openapi: new OpenApiOperation(
                tags: ['Node'],
                summary: 'Lists nodes with pagination metadata.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final readonly class NodeCollectionOutput
{
    /** @param list<NodeOutput> $items */
    public function __construct(
        public array $items,
        public PaginationMetadata $metadata,
    ) {
    }
}
