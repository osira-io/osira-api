<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\NodeGroup\Presentation\Api\Dto\CreateNodeGroupInput;
use App\NodeGroup\Presentation\Api\Dto\UpdateNodeGroupInput;
use App\NodeGroup\Presentation\Api\Processor\CreateNodeGroupProcessor;
use App\NodeGroup\Presentation\Api\Processor\DeleteNodeGroupProcessor;
use App\NodeGroup\Presentation\Api\Processor\UpdateNodeGroupProcessor;
use App\NodeGroup\Presentation\Api\Provider\NodeGroupProvider;
use App\Rbac\Infrastructure\Security\PermissionCode;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'NodeGroup',
    operations: [
        new Get(
            uriTemplate: '/node-groups/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::NODE_GROUPS_READ."')",
            provider: NodeGroupProvider::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Post(
            uriTemplate: '/node-groups',
            status: Response::HTTP_CREATED,
            security: "is_granted('".PermissionCode::NODE_GROUPS_CREATE."')",
            input: CreateNodeGroupInput::class,
            output: self::class,
            read: false,
            processor: CreateNodeGroupProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Patch(
            uriTemplate: '/node-groups/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::NODE_GROUPS_UPDATE."')",
            input: UpdateNodeGroupInput::class,
            output: self::class,
            read: false,
            processor: UpdateNodeGroupProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Delete(
            uriTemplate: '/node-groups/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::NODE_GROUPS_DELETE."')",
            read: false,
            processor: DeleteNodeGroupProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class NodeGroupOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $name,
        public ?string $description,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
