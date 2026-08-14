<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\UpdateNodeInput;
use App\Security\PermissionCode;
use App\State\NodeProvider;
use App\State\UpdateNodeProcessor;

#[ApiResource(
    shortName: 'Node',
    operations: [
        new Get(
            uriTemplate: '/nodes/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::NODES_READ."')",
            provider: NodeProvider::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Patch(
            uriTemplate: '/nodes/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::NODES_UPDATE."')",
            input: UpdateNodeInput::class,
            output: self::class,
            read: false,
            processor: UpdateNodeProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class NodeOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $hostname,
        public ?string $displayName,
        public string $os,
        public string $architecture,
        public ?string $environment,
        /** @var list<string> */
        public array $tags,
        /** @var list<NodeGroupSummary> */
        public array $groups,
        public \DateTimeImmutable $firstSeenAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
