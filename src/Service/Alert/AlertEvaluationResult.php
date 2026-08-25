<?php

declare(strict_types=1);

namespace App\Service\Alert;

final readonly class AlertEvaluationResult
{
    /** @param array<string, string> $labels */
    public function __construct(
        public AlertEvaluationStatus $status,
        public ?string $observedValue,
        public array $labels,
        public \DateTimeImmutable $evaluatedAt,
        public int $matchingOccurrences = 0,
        public ?string $error = null,
    ) {
    }
}
