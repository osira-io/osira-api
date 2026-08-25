<?php

declare(strict_types=1);

namespace App\Entity\Monitoring;

use App\Repository\Monitoring\ItemDefinitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: ItemDefinitionRepository::class)]
#[ORM\Table(name: 'item_definitions')]
#[ORM\UniqueConstraint(name: 'uniq_item_definitions_key', columns: ['key_name'])]
final class ItemDefinition
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private readonly Ulid $id;

    #[ORM\Column(name: 'key_name', length: 128)]
    private string $key;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $unit;

    #[ORM\Column(enumType: ItemValueType::class, length: 16)]
    private ItemValueType $valueType;

    #[ORM\Column]
    private int $intervalSeconds;

    #[ORM\Column(nullable: true)]
    private ?int $timeoutSeconds;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $linuxCommand;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $windowsCommand;

    #[ORM\Column(options: ['default' => true])]
    private bool $isEnabled;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, MonitoringTemplate> */
    #[ORM\ManyToMany(targetEntity: MonitoringTemplate::class, mappedBy: 'itemDefinitions')]
    private Collection $monitoringTemplates;

    public function __construct(
        string $key,
        string $name,
        ?string $description,
        ?string $unit,
        ItemValueType $valueType,
        int $intervalSeconds,
        ?int $timeoutSeconds,
        ?string $linuxCommand,
        ?string $windowsCommand,
        bool $isEnabled,
        \DateTimeImmutable $now,
    ) {
        $this->id = new Ulid();
        $this->key = $key;
        $this->name = $name;
        $this->description = $description;
        $this->unit = $unit;
        $this->valueType = $valueType;
        $this->intervalSeconds = $intervalSeconds;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->linuxCommand = $linuxCommand;
        $this->windowsCommand = $windowsCommand;
        $this->isEnabled = $isEnabled;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->monitoringTemplates = new ArrayCollection();
    }

    public function update(
        string $key,
        string $name,
        ?string $description,
        ?string $unit,
        ItemValueType $valueType,
        int $intervalSeconds,
        ?int $timeoutSeconds,
        ?string $linuxCommand,
        ?string $windowsCommand,
        bool $isEnabled,
        \DateTimeImmutable $now,
    ): void {
        $this->key = $key;
        $this->name = $name;
        $this->description = $description;
        $this->unit = $unit;
        $this->valueType = $valueType;
        $this->intervalSeconds = $intervalSeconds;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->linuxCommand = $linuxCommand;
        $this->windowsCommand = $windowsCommand;
        $this->isEnabled = $isEnabled;
        $this->updatedAt = $now;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function unit(): ?string
    {
        return $this->unit;
    }

    public function valueType(): ItemValueType
    {
        return $this->valueType;
    }

    public function intervalSeconds(): int
    {
        return $this->intervalSeconds;
    }

    public function timeoutSeconds(): ?int
    {
        return $this->timeoutSeconds;
    }

    public function linuxCommand(): ?string
    {
        return $this->linuxCommand;
    }

    public function windowsCommand(): ?string
    {
        return $this->windowsCommand;
    }

    public function commandForOs(string $os): ?string
    {
        return match (mb_strtolower(trim($os))) {
            'linux' => $this->linuxCommand,
            'windows' => $this->windowsCommand,
            default => null,
        };
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, MonitoringTemplate> */
    public function monitoringTemplates(): Collection
    {
        return $this->monitoringTemplates;
    }
}
