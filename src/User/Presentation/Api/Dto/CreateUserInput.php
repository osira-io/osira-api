<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateUserInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 12, max: 4096)]
    public string $password = '';

    /** @var list<string> */
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Ulid()])]
    public array $roleIds = [];
}
