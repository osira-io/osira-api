<?php

declare(strict_types=1);

namespace App\Factory\Alert;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Monitoring\ItemDefinition;
use App\Service\Shared\Exception\ResourceValidationException;

final class AlertRuleFactory
{
    public function create(string $name, ?string $description, ItemDefinition $itemDefinition, AlertOperator $operator, string $expectedValue, ?string $recoveryThreshold, int $evaluationWindowSeconds, int $requiredOccurrences, AlertSeverity $severity, AlertRuleImpactType $impactType, bool $isEnabled, \DateTimeImmutable $now): AlertRule
    {
        return new AlertRule($this->normalizeName($name), $this->normalizeDescription($description), $itemDefinition, $operator, $expectedValue, $recoveryThreshold, $evaluationWindowSeconds, $requiredOccurrences, $severity, $impactType, $isEnabled, $now);
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ResourceValidationException('An alert rule name cannot be empty.');
        }

        return $name;
    }

    public function normalizeDescription(?string $description): ?string
    {
        if (null === $description) {
            return null;
        }
        $description = trim($description);

        return '' === $description ? null : $description;
    }

    public function operator(string $value): AlertOperator
    {
        return AlertOperator::tryFrom($value) ?? throw new ResourceValidationException('operator is invalid.');
    }

    public function severity(string $value): AlertSeverity
    {
        return AlertSeverity::tryFrom($value) ?? throw new ResourceValidationException('severity is invalid.');
    }

    public function impactType(string $value): AlertRuleImpactType
    {
        return AlertRuleImpactType::tryFrom($value) ?? throw new ResourceValidationException('impactType is invalid.');
    }
}
