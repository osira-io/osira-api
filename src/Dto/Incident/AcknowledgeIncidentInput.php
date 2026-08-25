<?php

declare(strict_types=1);

namespace App\Dto\Incident;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AcknowledgeIncidentInput
{
    public function __construct(
        #[Assert\Length(max: 2000, normalizer: 'trim')]
        public ?string $message = null,
    ) {
    }
}
