<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\CreateUserInput;
use App\Dto\UpdateUserInput;
use App\Security\PermissionCode;
use App\State\CreateUserProcessor;
use App\State\DeleteUserProcessor;
use App\State\UpdateUserProcessor;
use App\State\UserProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(shortName: 'User', operations: [
    new Get(uriTemplate: '/users/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::USERS_READ."')", provider: UserProvider::class, openapi: new OpenApiOperation(security: [['JWT' => []]])),
    new Post(uriTemplate: '/users', status: Response::HTTP_CREATED, security: "is_granted('".PermissionCode::USERS_CREATE."')", input: CreateUserInput::class, output: self::class, read: false, processor: CreateUserProcessor::class, openapi: new OpenApiOperation(security: [['JWT' => []]])),
    new Patch(uriTemplate: '/users/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::USERS_UPDATE."')", input: UpdateUserInput::class, output: self::class, read: false, processor: UpdateUserProcessor::class, openapi: new OpenApiOperation(security: [['JWT' => []]])),
    new Delete(uriTemplate: '/users/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::USERS_DELETE."')", read: false, processor: DeleteUserProcessor::class, openapi: new OpenApiOperation(security: [['JWT' => []]])),
])]
final readonly class UserOutput
{
    /** @param list<RoleSummary> $roles */
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public string $email,
        public array $roles,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
