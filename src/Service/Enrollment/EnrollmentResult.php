<?php

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Entity\Agent\Agent;
use App\Entity\Node\Node;

final readonly class EnrollmentResult
{
    public function __construct(
        public Node $node,
        public Agent $agent,
        public string $rawAgentToken,
    ) {
    }
}
