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
use App\Dto\Node\NodeSummary;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Monitoring\CreateMonitoringTemplateProcessor;
use App\State\Processor\Monitoring\DeleteMonitoringTemplateProcessor;
use App\State\Processor\Monitoring\UpdateMonitoringTemplateProcessor;
use App\State\Provider\Monitoring\MonitoringTemplateProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    shortName: 'MonitoringTemplate',
    operations: [
        new Get(
            uriTemplate: '/monitoring-templates/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::MONITORING_TEMPLATES_READ."')",
            provider: MonitoringTemplateProvider::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Post(
            uriTemplate: '/monitoring-templates',
            status: Response::HTTP_CREATED,
            security: "is_granted('".PermissionCode::MONITORING_TEMPLATES_CREATE."')",
            input: CreateMonitoringTemplateInput::class,
            output: self::class,
            read: false,
            processor: CreateMonitoringTemplateProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Patch(
            uriTemplate: '/monitoring-templates/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::MONITORING_TEMPLATES_UPDATE."')",
            input: UpdateMonitoringTemplateInput::class,
            output: self::class,
            read: false,
            processor: UpdateMonitoringTemplateProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
        new Delete(
            uriTemplate: '/monitoring-templates/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::MONITORING_TEMPLATES_DELETE."')",
            read: false,
            processor: DeleteMonitoringTemplateProcessor::class,
            openapi: new OpenApiOperation(security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class MonitoringTemplateOutput
{
    /** @param list<ItemDefinitionSummary> $itemDefinitions
     * @param list<NodeSummary> $nodes
     * @param list<NodeGroupSummary> $nodeGroups
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public bool $isSystem,
        public bool $isEnabled,
        public array $itemDefinitions,
        public array $nodes,
        public array $nodeGroups,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
