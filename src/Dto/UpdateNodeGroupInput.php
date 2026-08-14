<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateNodeGroupInput
{
    private bool $nameProvided = false;
    private ?string $name = null;
    private bool $descriptionProvided = false;
    private ?string $description = null;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 128)]
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->nameProvided = true;
        $this->name = $name;
    }

    #[Ignore]
    public function isNameProvided(): bool
    {
        return $this->nameProvided;
    }

    #[Assert\Length(max: 2000)]
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->descriptionProvided = true;
        $this->description = $description;
    }

    #[Ignore]
    public function isDescriptionProvided(): bool
    {
        return $this->descriptionProvided;
    }
}
