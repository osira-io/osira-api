<?php

declare(strict_types=1);

namespace App\Dto\Alert;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Monitoring\ItemDefinitionSummary;
use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Alert\CreateAlertRuleProcessor;
use App\State\Processor\Alert\DeleteAlertRuleProcessor;
use App\State\Processor\Alert\UpdateAlertRuleProcessor;
use App\State\Provider\Alert\AlertRuleProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(shortName: 'AlertRule', operations: [
    new Get(uriTemplate: '/alert-rules/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::ALERT_RULES_READ."')", provider: AlertRuleProvider::class, openapi: new OpenApiOperation(tags: ['AlertRule'], summary: 'Gets an alert rule.', security: [['JWT' => []]])),
    new Post(uriTemplate: '/alert-rules', status: Response::HTTP_CREATED, security: "is_granted('".PermissionCode::ALERT_RULES_CREATE."')", input: CreateAlertRuleInput::class, output: self::class, read: false, processor: CreateAlertRuleProcessor::class, openapi: new OpenApiOperation(tags: ['AlertRule'], summary: 'Creates an alert rule targeting a single ItemDefinition.', description: 'impactType is mandatory and explicitly chosen by the caller: it is never inferred from the ItemDefinition key, severity, or operator. Only `availability` Incidents contribute to SLA downtime; `performance` and `informational` Incidents never do. The target ItemDefinition must produce an effective metric (not `string`-typed, V1 does not collect string metrics) for at least the scopes this rule is assigned to.', security: [['JWT' => []]])),
    new Patch(uriTemplate: '/alert-rules/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::ALERT_RULES_UPDATE."')", input: UpdateAlertRuleInput::class, output: self::class, read: false, processor: UpdateAlertRuleProcessor::class, openapi: new OpenApiOperation(tags: ['AlertRule'], summary: 'Updates an alert rule or its template, node group, and node assignments.', description: 'The target itemDefinitionId cannot be changed after creation. Omitting impactType preserves its current value; providing it always requires one of the three explicit values.', security: [['JWT' => []]])),
    new Delete(uriTemplate: '/alert-rules/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::ALERT_RULES_DELETE."')", read: false, processor: DeleteAlertRuleProcessor::class, openapi: new OpenApiOperation(tags: ['AlertRule'], summary: 'Deletes an alert rule.', description: 'Fails if the alert rule is still referenced by existing Incidents.', security: [['JWT' => []]])),
])]
final readonly class AlertRuleOutput
{
    /**
     * @param list<string> $monitoringTemplateIds
     * @param list<string> $nodeGroupIds
     * @param list<string> $nodeIds
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $name,
        public ?string $description,
        public ItemDefinitionSummary $itemDefinition,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => AlertOperator::VALUES], description: 'Numeric ItemValueType (float/integer) supports all six operators. Boolean and string ItemValueType support only eq/neq.')]
        public string $operator,
        public string $expectedValue,
        public ?string $recoveryThreshold,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => AlertSeverity::VALUES])]
        public string $severity,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => AlertRuleImpactType::VALUES], description: 'Explicitly chosen by the caller, never inferred. Only "availability" Incidents contribute to SLA downtime; "performance" and "informational" never do.')]
        public string $impactType,
        public int $evaluationWindowSeconds,
        public int $requiredOccurrences,
        public bool $isEnabled,
        public array $monitoringTemplateIds,
        public array $nodeGroupIds,
        public array $nodeIds,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
