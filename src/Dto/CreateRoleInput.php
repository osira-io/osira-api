<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateRoleInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    #[Assert\Length(max: 128)]
    public ?string $slug = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    /** @var list<string> */
    #[Assert\All([new Assert\Regex(pattern: '/^[a-z_]+\.[a-z_]+$/')])]
    public array $permissionCodes = [];
}
