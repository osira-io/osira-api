<?php

declare(strict_types=1);

namespace App\Dto\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Shared\PaginationMetadata;
use App\Security\Rbac\PermissionCode;
use App\Service\Shared\PaginationParameters;
use App\State\Provider\User\UserCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'UserCollection', operations: [
    new Get(
        uriTemplate: '/users',
        parameters: [
            'page' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1], constraints: [new Assert\Positive()], castToNativeType: true),
            'itemsPerPage' => new QueryParameter(schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE], constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)], castToNativeType: true),
        ],
        strictQueryParameterValidation: true,
        security: "is_granted('".PermissionCode::USERS_READ."')",
        provider: UserCollectionProvider::class,
        openapi: new OpenApiOperation(tags: ['User'], summary: 'Lists users and role assignments.', security: [['JWT' => []]]),
    ),
])]
final readonly class UserCollectionOutput
{
    /** @param list<UserOutput> $items */
    public function __construct(public array $items, public PaginationMetadata $metadata)
    {
    }
}
