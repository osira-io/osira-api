<?php

declare(strict_types=1);

namespace App\Dto\Sla;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Node\NodeSummary;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Sla\CreateSlaProcessor;
use App\State\Processor\Sla\DeleteSlaProcessor;
use App\State\Processor\Sla\UpdateSlaProcessor;
use App\State\Provider\Sla\SlaProvider;
use App\State\Provider\Sla\SlaReportProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'Sla', operations: [
    new Get(uriTemplate: '/slas/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::SLAS_READ."')", provider: SlaProvider::class, openapi: new OpenApiOperation(tags: ['Sla'], summary: 'Gets an SLA configuration.', security: [['JWT' => []]])),
    new Get(uriTemplate: '/slas/{id}/report', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], parameters: [
        'from' => new QueryParameter(schema: ['type' => 'string', 'format' => 'date-time'], constraints: [new Assert\DateTime(format: \DateTimeInterface::ATOM)]),
        'to' => new QueryParameter(schema: ['type' => 'string', 'format' => 'date-time'], constraints: [new Assert\DateTime(format: \DateTimeInterface::ATOM)]),
    ], strictQueryParameterValidation: true, security: "is_granted('".PermissionCode::SLAS_READ."')", output: SlaReportOutput::class, provider: SlaReportProvider::class, openapi: new OpenApiOperation(tags: ['Sla'], summary: 'Calculates an SLA report from PostgreSQL incidents and maintenance windows.', description: 'For multi-node scopes, global availability is weighted by eligible node-seconds: sum(uptime) / sum(eligible).', security: [['JWT' => []]])),
    new Post(uriTemplate: '/slas', status: Response::HTTP_CREATED, security: "is_granted('".PermissionCode::SLAS_CREATE."')", input: CreateSlaInput::class, output: self::class, read: false, processor: CreateSlaProcessor::class, openapi: new OpenApiOperation(tags: ['Sla'], summary: 'Creates an SLA objective and its Node or NodeGroup scopes.', security: [['JWT' => []]])),
    new Patch(uriTemplate: '/slas/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::SLAS_UPDATE."')", input: UpdateSlaInput::class, output: self::class, read: false, processor: UpdateSlaProcessor::class, openapi: new OpenApiOperation(tags: ['Sla'], summary: 'Updates an SLA objective or its scopes.', security: [['JWT' => []]])),
    new Delete(uriTemplate: '/slas/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::SLAS_DELETE."')", read: false, processor: DeleteSlaProcessor::class, openapi: new OpenApiOperation(tags: ['Sla'], summary: 'Deletes an SLA configuration.', security: [['JWT' => []]])),
])]
final readonly class SlaOutput
{
    /**
     * @param list<NodeSummary> $nodes
     * @param list<NodeGroupSummary> $nodeGroups
     */
    public function __construct(#[ApiProperty(identifier: true)] public string $id, public string $name, public ?string $description, public float $targetPercentage, public string $periodType, public bool $excludeMaintenance, public bool $isEnabled, public array $nodes, public array $nodeGroups, public \DateTimeImmutable $createdAt, public \DateTimeImmutable $updatedAt)
    {
    }
}
