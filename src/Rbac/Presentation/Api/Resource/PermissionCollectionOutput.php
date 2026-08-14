<?php

declare(strict_types=1);

namespace App\Rbac\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Rbac\Infrastructure\Security\PermissionCode;
use App\Rbac\Presentation\Api\Provider\PermissionCollectionProvider;
use App\Shared\Application\Pagination\PaginationParameters;
use App\Shared\Presentation\Api\Resource\PaginationMetadata;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'PermissionCollection', operations: [
    new Get(
        uriTemplate: '/permissions',
        parameters: [
            'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], constraints: [new Assert\Positive()], castToNativeType: true),
            'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE], constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)], castToNativeType: true),
        ],
        strictQueryParameterValidation: true,
        security: "is_granted('".PermissionCode::PERMISSIONS_READ."')",
        provider: PermissionCollectionProvider::class,
        openapi: new OpenApiOperation(tags: ['Permission'], summary: 'Lists the immutable Osira permission catalog.', security: [['JWT' => []]]),
    ),
])]
final readonly class PermissionCollectionOutput
{
    /** @param list<PermissionOutput> $items */
    public function __construct(public array $items, public PaginationMetadata $metadata)
    {
    }
}
