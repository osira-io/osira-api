<?php

declare(strict_types=1);

namespace App\Factory\Incident;

use App\Entity\Alert\AlertRule;
use App\Entity\Incident\Incident;
use App\Entity\Node\Node;

final class IncidentFactory
{
    /** @param array<string, string> $labels */
    public function create(Node $node, AlertRule $rule, array $labels, string $lastValue, \DateTimeImmutable $now): Incident
    {
        ksort($labels);

        return new Incident(
            $node,
            $rule,
            $rule->severity(),
            $rule->title(),
            $rule->message(),
            $labels,
            self::identity($node, $rule, $labels),
            $lastValue,
            $now,
            $now,
            $now,
        );
    }

    /** @param array<string, string> $labels */
    public static function identity(Node $node, AlertRule $rule, array $labels): string
    {
        ksort($labels);

        return hash('sha256', json_encode([(string) $node->id(), (string) $rule->id(), $labels], \JSON_THROW_ON_ERROR));
    }
}
