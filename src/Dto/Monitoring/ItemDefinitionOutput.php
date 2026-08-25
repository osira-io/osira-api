<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Monitoring\CreateItemDefinitionProcessor;
use App\State\Processor\Monitoring\DeleteItemDefinitionProcessor;
use App\State\Processor\Monitoring\UpdateItemDefinitionProcessor;
use App\State\Provider\Monitoring\ItemDefinitionProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'ItemDefinition',
    operations: [
        new Get(
            uriTemplate: '/item-definitions/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::ITEM_DEFINITIONS_READ."')",
            provider: ItemDefinitionProvider::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Post(
            uriTemplate: '/item-definitions',
            status: Response::HTTP_CREATED,
            security: "is_granted('".PermissionCode::ITEM_DEFINITIONS_CREATE."')",
            input: CreateItemDefinitionInput::class,
            output: self::class,
            read: false,
            processor: CreateItemDefinitionProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Patch(
            uriTemplate: '/item-definitions/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::ITEM_DEFINITIONS_UPDATE."')",
            input: UpdateItemDefinitionInput::class,
            output: self::class,
            read: false,
            processor: UpdateItemDefinitionProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Delete(
            uriTemplate: '/item-definitions/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::ITEM_DEFINITIONS_DELETE."')",
            read: false,
            processor: DeleteItemDefinitionProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class ItemDefinitionOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $key,
        public string $name,
        public ?string $description,
        public ?string $unit,
        public string $valueType,
        public int $intervalSeconds,
        public ?int $timeoutSeconds,
        public ?string $linuxCommand,
        public ?string $windowsCommand,
        public bool $isEnabled,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
