<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

use App\Entity\Monitoring\ItemValueType;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateItemDefinitionInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $key = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\Length(max: 128)]
    public ?string $category = null;

    #[Assert\Length(max: 32)]
    public ?string $unit = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [ItemValueType::class, 'values'])]
    public string $valueType = '';

    #[Assert\Positive]
    public int $intervalSeconds = 60;

    #[Assert\Positive]
    public ?int $timeoutSeconds = null;

    public bool $isEnabled = true;
}
