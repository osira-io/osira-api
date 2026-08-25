<?php

declare(strict_types=1);

namespace App\Dto\Maintenance;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Node\NodeSummary;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Maintenance\CreateMaintenanceWindowProcessor;
use App\State\Processor\Maintenance\DeleteMaintenanceWindowProcessor;
use App\State\Processor\Maintenance\UpdateMaintenanceWindowProcessor;
use App\State\Provider\Maintenance\MaintenanceWindowProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'MaintenanceWindow',
    operations: [
        new Get(
            uriTemplate: '/maintenance-windows/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::MAINTENANCE_WINDOWS_READ."')",
            provider: MaintenanceWindowProvider::class,
            openapi: new OpenApiOperation(
                tags: ['MaintenanceWindow'],
                summary: 'Gets a maintenance window.',
                description: 'Maintenance windows suppress new incidents while active, but they never resolve existing incidents.',
                security: [['JWT' => []]],
            ),
        ),
        new Post(
            uriTemplate: '/maintenance-windows',
            status: Response::HTTP_CREATED,
            security: "is_granted('".PermissionCode::MAINTENANCE_WINDOWS_CREATE."')",
            input: CreateMaintenanceWindowInput::class,
            output: self::class,
            read: false,
            processor: CreateMaintenanceWindowProcessor::class,
            openapi: new OpenApiOperation(
                tags: ['MaintenanceWindow'],
                summary: 'Creates a maintenance window.',
                description: 'The interval requires explicit timezone offsets and is stored in UTC.',
                security: [['JWT' => []]],
            ),
        ),
        new Patch(
            uriTemplate: '/maintenance-windows/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::MAINTENANCE_WINDOWS_UPDATE."')",
            input: UpdateMaintenanceWindowInput::class,
            output: self::class,
            read: false,
            processor: UpdateMaintenanceWindowProcessor::class,
            openapi: new OpenApiOperation(tags: ['MaintenanceWindow'], summary: 'Updates a maintenance window.', security: [['JWT' => []]]),
        ),
        new Delete(
            uriTemplate: '/maintenance-windows/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::MAINTENANCE_WINDOWS_DELETE."')",
            read: false,
            processor: DeleteMaintenanceWindowProcessor::class,
            openapi: new OpenApiOperation(tags: ['MaintenanceWindow'], summary: 'Deletes a maintenance window.', security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class MaintenanceWindowOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $name,
        public ?string $description,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public bool $isEnabled,
        /** @var list<NodeSummary> */
        public array $nodes,
        /** @var list<NodeGroupSummary> */
        public array $nodeGroups,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
