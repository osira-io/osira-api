<?php

declare(strict_types=1);

namespace App\Enrollment\Application\Service;

use App\Agent\Domain\Entity\Agent;
use App\Node\Domain\Entity\Node;

final readonly class EnrollmentResult
{
    public function __construct(
        public Node $node,
        public Agent $agent,
        public string $rawAgentToken,
    ) {
    }
}
