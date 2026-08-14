<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\PermissionCode;
use App\State\PermissionProvider;

#[ApiResource(
    shortName: 'Permission',
    operations: [
        new Get(
            uriTemplate: '/permissions/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::PERMISSIONS_READ."')",
            provider: PermissionProvider::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class PermissionOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public ?string $category,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
