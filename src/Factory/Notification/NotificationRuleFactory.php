<?php

declare(strict_types=1);

namespace App\Factory\Notification;

use App\Entity\Alert\AlertSeverity;
use App\Entity\Notification\NotificationRule;
use App\Service\Shared\Exception\ResourceValidationException;

final readonly class NotificationRuleFactory
{
    /** @param list<AlertSeverity> $severities */
    public function create(string $name, bool $isEnabled, array $severities, \DateTimeImmutable $now): NotificationRule
    {
        return new NotificationRule($this->normalizeName($name), $isEnabled, $this->normalizeSeverities($severities), $now);
    }

    public function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new ResourceValidationException('Notification rule name cannot be blank.');
        }

        return $name;
    }

    /** @param list<AlertSeverity> $severities
     * @return list<AlertSeverity>
     */
    public function normalizeSeverities(array $severities): array
    {
        $unique = [];
        foreach ($severities as $severity) {
            $unique[$severity->value] = $severity;
        }
        if ([] === $unique) {
            throw new ResourceValidationException('At least one severity is required.');
        }

        return array_values($unique);
    }
}
