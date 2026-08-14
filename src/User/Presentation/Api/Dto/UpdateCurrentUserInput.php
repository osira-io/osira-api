<?php

declare(strict_types=1);

namespace App\User\Presentation\Api\Dto;

use App\Shared\Application\Internationalization\SupportedLocale;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateCurrentUserInput
{
    private string $locale = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    #[Assert\Choice(callback: [SupportedLocale::class, 'all'], message: 'The locale {{ value }} is not supported.')]
    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = SupportedLocale::normalize($locale);
    }
}
