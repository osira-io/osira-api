<?php

declare(strict_types=1);

namespace App\Dto\Maintenance;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMaintenanceWindowInput
{
    private bool $nameProvided = false;
    private ?string $name = null;
    private bool $descriptionProvided = false;
    private ?string $description = null;
    private bool $startsAtProvided = false;
    private ?string $startsAt = null;
    private bool $endsAtProvided = false;
    private ?string $endsAt = null;
    private bool $isEnabledProvided = false;
    private ?bool $isEnabled = null;
    private bool $nodeIdsProvided = false;
    /** @var list<string> */
    private array $nodeIds = [];
    private bool $nodeGroupIdsProvided = false;
    /** @var list<string> */
    private array $nodeGroupIds = [];

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

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Regex(pattern: '/(?:Z|[+-]\d{2}:\d{2})$/', message: 'startsAt must include an explicit timezone offset.')]
    public function getStartsAt(): ?string
    {
        return $this->startsAt;
    }

    public function setStartsAt(?string $startsAt): void
    {
        $this->startsAtProvided = true;
        $this->startsAt = $startsAt;
    }

    #[Ignore]
    public function isStartsAtProvided(): bool
    {
        return $this->startsAtProvided;
    }

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Regex(pattern: '/(?:Z|[+-]\d{2}:\d{2})$/', message: 'endsAt must include an explicit timezone offset.')]
    public function getEndsAt(): ?string
    {
        return $this->endsAt;
    }

    public function setEndsAt(?string $endsAt): void
    {
        $this->endsAtProvided = true;
        $this->endsAt = $endsAt;
    }

    #[Ignore]
    public function isEndsAtProvided(): bool
    {
        return $this->endsAtProvided;
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

    /** @return list<string> */
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public function getNodeIds(): array
    {
        return $this->nodeIds;
    }

    /** @param list<string> $nodeIds */
    public function setNodeIds(array $nodeIds): void
    {
        $this->nodeIdsProvided = true;
        $this->nodeIds = $nodeIds;
    }

    #[Ignore]
    public function areNodeIdsProvided(): bool
    {
        return $this->nodeIdsProvided;
    }

    /** @return list<string> */
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public function getNodeGroupIds(): array
    {
        return $this->nodeGroupIds;
    }

    /** @param list<string> $nodeGroupIds */
    public function setNodeGroupIds(array $nodeGroupIds): void
    {
        $this->nodeGroupIdsProvided = true;
        $this->nodeGroupIds = $nodeGroupIds;
    }

    #[Ignore]
    public function areNodeGroupIdsProvided(): bool
    {
        return $this->nodeGroupIdsProvided;
    }
}
