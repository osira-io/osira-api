<?php

declare(strict_types=1);

namespace App\Application\Enrollment;

use App\Entity\Agent;
use App\Entity\Node;

final readonly class EnrollmentResult
{
    public function __construct(
        public Node $node,
        public Agent $agent,
        public string $rawAgentToken,
    ) {
    }
}
