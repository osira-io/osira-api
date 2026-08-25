<?php

declare(strict_types=1);

namespace App\Dto\Maintenance;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Shared\PaginationMetadata;
use App\Security\Rbac\PermissionCode;
use App\Service\Shared\PaginationParameters;
use App\State\Provider\Maintenance\MaintenanceWindowCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'MaintenanceWindowCollection',
    operations: [
        new Get(
            uriTemplate: '/maintenance-windows',
            parameters: [
                'page' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Page number, starting at 1.',
                    constraints: [new Assert\Positive()],
                    castToNativeType: true,
                ),
                'itemsPerPage' => new QueryParameter(
                    schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => PaginationParameters::MAX_ITEMS_PER_PAGE, 'default' => PaginationParameters::DEFAULT_ITEMS_PER_PAGE],
                    description: 'Number of maintenance windows returned per page.',
                    constraints: [new Assert\Positive(), new Assert\LessThanOrEqual(PaginationParameters::MAX_ITEMS_PER_PAGE)],
                    castToNativeType: true,
                ),
            ],
            strictQueryParameterValidation: true,
            security: "is_granted('".PermissionCode::MAINTENANCE_WINDOWS_READ."')",
            provider: MaintenanceWindowCollectionProvider::class,
            openapi: new OpenApiOperation(
                tags: ['MaintenanceWindow'],
                summary: 'Lists maintenance windows with pagination metadata.',
                description: 'Active maintenance windows suppress new incidents for targeted nodes. Existing incidents remain in their current lifecycle.',
                security: [['JWT' => []]],
            ),
        ),
    ],
)]
final readonly class MaintenanceWindowCollectionOutput
{
    /** @param list<MaintenanceWindowOutput> $items */
    public function __construct(
        public array $items,
        public PaginationMetadata $metadata,
    ) {
    }
}
