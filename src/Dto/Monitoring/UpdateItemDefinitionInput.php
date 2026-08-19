<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

use App\Entity\Monitoring\ItemValueType;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateItemDefinitionInput
{
    private bool $keyProvided = false;
    private ?string $key = null;
    private bool $nameProvided = false;
    private ?string $name = null;
    private bool $descriptionProvided = false;
    private ?string $description = null;
    private bool $categoryProvided = false;
    private ?string $category = null;
    private bool $unitProvided = false;
    private ?string $unit = null;
    private bool $valueTypeProvided = false;
    private ?string $valueType = null;
    private bool $intervalSecondsProvided = false;
    private ?int $intervalSeconds = null;
    private bool $timeoutSecondsProvided = false;
    private ?int $timeoutSeconds = null;
    private bool $isEnabledProvided = false;
    private ?bool $isEnabled = null;

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 128)]
    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(?string $key): void
    {
        $this->keyProvided = true;
        $this->key = $key;
    }

    #[Ignore]
    public function isKeyProvided(): bool
    {
        return $this->keyProvided;
    }

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

    #[Assert\Length(max: 128)]
    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): void
    {
        $this->categoryProvided = true;
        $this->category = $category;
    }

    #[Ignore]
    public function isCategoryProvided(): bool
    {
        return $this->categoryProvided;
    }

    #[Assert\Length(max: 32)]
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): void
    {
        $this->unitProvided = true;
        $this->unit = $unit;
    }

    #[Ignore]
    public function isUnitProvided(): bool
    {
        return $this->unitProvided;
    }

    #[Assert\Choice(callback: [ItemValueType::class, 'values'])]
    public function getValueType(): ?string
    {
        return $this->valueType;
    }

    public function setValueType(?string $valueType): void
    {
        $this->valueTypeProvided = true;
        $this->valueType = $valueType;
    }

    #[Ignore]
    public function isValueTypeProvided(): bool
    {
        return $this->valueTypeProvided;
    }

    #[Assert\Positive]
    public function getIntervalSeconds(): ?int
    {
        return $this->intervalSeconds;
    }

    public function setIntervalSeconds(?int $intervalSeconds): void
    {
        $this->intervalSecondsProvided = true;
        $this->intervalSeconds = $intervalSeconds;
    }

    #[Ignore]
    public function isIntervalSecondsProvided(): bool
    {
        return $this->intervalSecondsProvided;
    }

    #[Assert\Positive]
    public function getTimeoutSeconds(): ?int
    {
        return $this->timeoutSeconds;
    }

    public function setTimeoutSeconds(?int $timeoutSeconds): void
    {
        $this->timeoutSecondsProvided = true;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    #[Ignore]
    public function isTimeoutSecondsProvided(): bool
    {
        return $this->timeoutSecondsProvided;
    }

    public function getIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): void
    {
        $this->isEnabledProvided = true;
        $this->isEnabled = $isEnabled;
    }

    #[Ignore]
    public function isIsEnabledProvided(): bool
    {
        return $this->isEnabledProvided;
    }
}
