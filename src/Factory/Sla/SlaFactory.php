<?php

declare(strict_types=1);

namespace App\Factory\Sla;

use App\Entity\Sla\Sla;
use App\Entity\Sla\SlaPeriodType;
use App\Service\Shared\Exception\ResourceValidationException;

final class SlaFactory
{
    public function create(string $name, ?string $description, float $targetPercentage, string $periodType, bool $excludeMaintenance, bool $isEnabled, \DateTimeImmutable $now): Sla
    {
        return new Sla($this->normalizeName($name), $this->normalizeDescription($description), $this->normalizeTarget($targetPercentage), $this->periodType($periodType), $excludeMaintenance, $isEnabled, $now);
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ResourceValidationException('An SLA name cannot be empty.');
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

    public function normalizeTarget(float $target): float
    {
        if (!is_finite($target) || $target < 0 || $target > 100) {
            throw new ResourceValidationException('targetPercentage must be between 0 and 100.');
        }

        return round($target, 3);
    }

    public function periodType(string $value): SlaPeriodType
    {
        return SlaPeriodType::tryFrom($value) ?? throw new ResourceValidationException('periodType is invalid.');
    }
}
