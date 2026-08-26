<?php

declare(strict_types=1);

namespace App\Dto\Sla;

use App\Entity\Sla\SlaPeriodType;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateSlaInput
{
    private bool $nameProvided = false;
    private ?string $name = null;
    private bool $descriptionProvided = false;
    private ?string $description = null;
    private bool $targetProvided = false;
    private ?float $targetPercentage = null;
    private bool $periodProvided = false;
    private ?string $periodType = null;
    private bool $excludeProvided = false;
    private ?bool $excludeMaintenance = null;
    private bool $enabledProvided = false;
    private ?bool $isEnabled = null;
    private bool $nodesProvided = false;
    /** @var list<string> */ private array $nodeIds = [];
    private bool $groupsProvided = false;
    /** @var list<string> */ private array $nodeGroupIds = [];

    #[Assert\NotBlank(allowNull: true)] #[Assert\Length(max: 128)]
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $value): void
    {
        $this->nameProvided = true;
        $this->name = $value;
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

    public function setDescription(?string $value): void
    {
        $this->descriptionProvided = true;
        $this->description = $value;
    }

    #[Ignore]
    public function isDescriptionProvided(): bool
    {
        return $this->descriptionProvided;
    }

    #[Assert\Range(min: 0, max: 100)]
    public function getTargetPercentage(): ?float
    {
        return $this->targetPercentage;
    }

    public function setTargetPercentage(?float $value): void
    {
        $this->targetProvided = true;
        $this->targetPercentage = $value;
    }

    #[Ignore]
    public function isTargetPercentageProvided(): bool
    {
        return $this->targetProvided;
    }

    #[Assert\Choice(choices: SlaPeriodType::VALUES)]
    public function getPeriodType(): ?string
    {
        return $this->periodType;
    }

    public function setPeriodType(?string $value): void
    {
        $this->periodProvided = true;
        $this->periodType = $value;
    }

    #[Ignore]
    public function isPeriodTypeProvided(): bool
    {
        return $this->periodProvided;
    }

    public function getExcludeMaintenance(): ?bool
    {
        return $this->excludeMaintenance;
    }

    public function setExcludeMaintenance(?bool $value): void
    {
        $this->excludeProvided = true;
        $this->excludeMaintenance = $value;
    }

    #[Ignore]
    public function isExcludeMaintenanceProvided(): bool
    {
        return $this->excludeProvided;
    }

    public function getIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $value): void
    {
        $this->enabledProvided = true;
        $this->isEnabled = $value;
    }

    #[Ignore]
    public function isIsEnabledProvided(): bool
    {
        return $this->enabledProvided;
    }

    /** @return list<string> */ #[Assert\Count(max: 100)] #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public function getNodeIds(): array
    {
        return $this->nodeIds;
    }

    /** @param list<string> $value */
    public function setNodeIds(array $value): void
    {
        $this->nodesProvided = true;
        $this->nodeIds = $value;
    }

    #[Ignore]
    public function areNodeIdsProvided(): bool
    {
        return $this->nodesProvided;
    }

    /** @return list<string> */ #[Assert\Count(max: 100)] #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public function getNodeGroupIds(): array
    {
        return $this->nodeGroupIds;
    }

    /** @param list<string> $value */
    public function setNodeGroupIds(array $value): void
    {
        $this->groupsProvided = true;
        $this->nodeGroupIds = $value;
    }

    #[Ignore]
    public function areNodeGroupIdsProvided(): bool
    {
        return $this->groupsProvided;
    }
}
