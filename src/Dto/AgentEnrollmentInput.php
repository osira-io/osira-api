<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AgentEnrollmentInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    #[Assert\Regex(pattern: '/^osi_enroll_[A-Za-z0-9_-]+$/')]
    public string $enrollmentToken = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$/')]
    public string $hostname = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9._-]*$/')]
    public string $os = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[A-Za-z0-9][A-Za-z0-9._-]*$/')]
    public string $architecture = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/')]
    public string $agentVersion = '';
}
