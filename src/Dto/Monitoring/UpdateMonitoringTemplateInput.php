<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMonitoringTemplateInput
{
    private bool $nameProvided = false;
    private ?string $name = null;
    private bool $slugProvided = false;
    private ?string $slug = null;
    private bool $descriptionProvided = false;
    private ?string $description = null;
    private bool $itemDefinitionIdsProvided = false;
    /** @var list<string> */
    private array $itemDefinitionIds = [];
    private bool $isEnabledProvided = false;
    private ?bool $isEnabled = null;

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

    #[Assert\Length(max: 128)]
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slugProvided = true;
        $this->slug = $slug;
    }

    #[Ignore]
    public function isSlugProvided(): bool
    {
        return $this->slugProvided;
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

    /** @return list<string> */
    #[Assert\Count(max: 200)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public function getItemDefinitionIds(): array
    {
        return $this->itemDefinitionIds;
    }

    /** @param list<string> $itemDefinitionIds */
    public function setItemDefinitionIds(array $itemDefinitionIds): void
    {
        $this->itemDefinitionIdsProvided = true;
        $this->itemDefinitionIds = $itemDefinitionIds;
    }

    #[Ignore]
    public function areItemDefinitionIdsProvided(): bool
    {
        return $this->itemDefinitionIdsProvided;
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
