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
use App\Dto\CreateNodeGroupInput;
use App\Dto\UpdateNodeGroupInput;
use App\Security\PermissionCode;
use App\State\CreateNodeGroupProcessor;
use App\State\DeleteNodeGroupProcessor;
use App\State\NodeGroupProvider;
use App\State\UpdateNodeGroupProcessor;
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
