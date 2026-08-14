<?php

declare(strict_types=1);

namespace App\Rbac\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Rbac\Infrastructure\Security\PermissionCode;
use App\Rbac\Presentation\Api\Provider\RoleCollectionProvider;
use App\Shared\Application\Pagination\PaginationParameters;
use App\Shared\Presentation\Api\Resource\PaginationMetadata;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'RoleCollection', operations: [
    new Get(
        uriTemplate: '/roles',
        parameters: [
            'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], constraints: [new Assert\Positive()], castToNativeType: true),
            'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE], constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)], castToNativeType: true),
        ],
        strictQueryParameterValidation: true,
        security: "is_granted('".PermissionCode::ROLES_READ."')",
        provider: RoleCollectionProvider::class,
        openapi: new OpenApiOperation(tags: ['Role'], summary: 'Lists roles with their permissions.', security: [['JWT' => []]]),
    ),
])]
final readonly class RoleCollectionOutput
{
    /** @param list<RoleOutput> $items */
    public function __construct(public array $items, public PaginationMetadata $metadata)
    {
    }
}
