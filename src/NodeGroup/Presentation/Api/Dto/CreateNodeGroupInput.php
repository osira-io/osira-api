<?php

declare(strict_types=1);

namespace App\NodeGroup\Presentation\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateNodeGroupInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;
}
