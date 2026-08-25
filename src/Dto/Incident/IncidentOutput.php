<?php

declare(strict_types=1);

namespace App\Dto\Incident;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Incident\IncidentStatus;
use App\Security\Rbac\PermissionCode;
use App\State\Provider\Incident\IncidentProvider;

#[ApiResource(
    shortName: 'Incident',
    operations: [
        new Get(
            uriTemplate: '/incidents/{id}',
            requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'],
            security: "is_granted('".PermissionCode::INCIDENTS_READ."')",
            provider: IncidentProvider::class,
            openapi: new OpenApiOperation(tags: ['Incident'], summary: 'Gets an incident produced by alert evaluation.', security: [['JWT' => []]]),
        ),
    ],
)]
final readonly class IncidentOutput
{
    /** @param array<string, string> $labels */
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public string $nodeId,
        public string $nodeHostname,
        public string $alertRuleId,
        public string $alertRuleName,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => [IncidentStatus::FIRING->value, IncidentStatus::RESOLVED->value]])]
        public string $status,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => [AlertSeverity::INFO->value, AlertSeverity::WARNING->value, AlertSeverity::CRITICAL->value]])]
        public string $severity,
        public string $title,
        public string $message,
        public array $labels,
        public \DateTimeImmutable $firstTriggeredAt,
        public \DateTimeImmutable $lastTriggeredAt,
        public ?\DateTimeImmutable $resolvedAt,
        public string $lastValue,
        public int $occurrences,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
