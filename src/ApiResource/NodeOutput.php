<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\State\NodeProvider;

#[ApiResource(
    shortName: 'Node',
    operations: [
        new GetCollection(
            uriTemplate: '/nodes',
            paginationEnabled: false,
            provider: NodeProvider::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Get(
            uriTemplate: '/nodes/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            provider: NodeProvider::class,
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
        public \DateTimeImmutable $firstSeenAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
