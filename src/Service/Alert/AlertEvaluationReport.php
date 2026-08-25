<?php

declare(strict_types=1);

namespace App\Service\Alert;

final readonly class AlertEvaluationReport
{
    public function __construct(
        public int $nodes,
        public int $rules,
        public int $series,
        public int $firing,
        public int $ok,
        public int $noData,
        public int $errors,
    ) {
    }
}
