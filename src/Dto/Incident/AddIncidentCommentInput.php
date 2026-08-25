<?php

declare(strict_types=1);

namespace App\Dto\Incident;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddIncidentCommentInput
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 2000, normalizer: 'trim')]
        public string $message = '',
    ) {
    }
}
