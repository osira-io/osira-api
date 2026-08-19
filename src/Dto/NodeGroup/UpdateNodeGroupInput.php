<?php

declare(strict_types=1);

namespace App\Dto\NodeGroup;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateNodeGroupInput
{
    private bool $nameProvided = false;
    private ?string $name = null;
    private bool $descriptionProvided = false;
    private ?string $description = null;
    private bool $monitoringTemplateIdsProvided = false;
    /** @var list<string> */
    private array $monitoringTemplateIds = [];

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

    /** @return list<string> */
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public function getMonitoringTemplateIds(): array
    {
        return $this->monitoringTemplateIds;
    }

    /** @param list<string> $monitoringTemplateIds */
    public function setMonitoringTemplateIds(array $monitoringTemplateIds): void
    {
        $this->monitoringTemplateIdsProvided = true;
        $this->monitoringTemplateIds = $monitoringTemplateIds;
    }

    #[Ignore]
    public function areMonitoringTemplateIdsProvided(): bool
    {
        return $this->monitoringTemplateIdsProvided;
    }
}
