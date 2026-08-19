<?php

declare(strict_types=1);

namespace App\Dto\Rbac;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Rbac\CreateRoleProcessor;
use App\State\Processor\Rbac\DeleteRoleProcessor;
use App\State\Processor\Rbac\UpdateRoleProcessor;
use App\State\Provider\Rbac\RoleProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(shortName: 'Role', operations: [
    new Get(uriTemplate: '/roles/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::ROLES_READ."')", provider: RoleProvider::class, openapi: new OpenApiOperation(security: [['JWT' => []]])),
    new Post(uriTemplate: '/roles', status: Response::HTTP_CREATED, security: "is_granted('".PermissionCode::ROLES_CREATE."')", input: CreateRoleInput::class, output: self::class, read: false, processor: CreateRoleProcessor::class, openapi: new OpenApiOperation(security: [['JWT' => []]])),
    new Patch(uriTemplate: '/roles/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::ROLES_UPDATE."')", input: UpdateRoleInput::class, output: self::class, read: false, processor: UpdateRoleProcessor::class, openapi: new OpenApiOperation(security: [['JWT' => []]])),
    new Delete(uriTemplate: '/roles/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::ROLES_DELETE."')", read: false, processor: DeleteRoleProcessor::class, openapi: new OpenApiOperation(security: [['JWT' => []]])),
])]
final readonly class RoleOutput
{
    /** @param list<PermissionOutput> $permissions */
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public bool $isSystem,
        public array $permissions,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
