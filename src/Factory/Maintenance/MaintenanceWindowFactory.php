<?php

declare(strict_types=1);

namespace App\Factory\Maintenance;

use App\Entity\Maintenance\MaintenanceWindow;
use App\Service\Shared\Exception\ResourceValidationException;

final class MaintenanceWindowFactory
{
    public function create(
        string $name,
        ?string $description,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        bool $isEnabled,
        \DateTimeImmutable $now,
    ): MaintenanceWindow {
        $this->assertInterval($startsAt, $endsAt);

        return new MaintenanceWindow(
            $this->normalizeName($name),
            $this->normalizeDescription($description),
            $this->utc($startsAt),
            $this->utc($endsAt),
            $isEnabled,
            $this->utc($now),
        );
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ResourceValidationException('A maintenance window name cannot be empty.');
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

    public function parseDateTime(string $value, string $field): \DateTimeImmutable
    {
        if (1 !== preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new ResourceValidationException(\sprintf('%s must include an explicit timezone offset.', $field));
        }

        try {
            return $this->utc(new \DateTimeImmutable($value));
        } catch (\Exception) {
            throw new ResourceValidationException(\sprintf('%s must be a valid date-time.', $field));
        }
    }

    public function assertInterval(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): void
    {
        if ($endsAt <= $startsAt) {
            throw new ResourceValidationException('endsAt must be after startsAt.');
        }
    }

    public function utc(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}
