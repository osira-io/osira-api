<?php

declare(strict_types=1);

namespace App\Dto\Alert;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAlertRuleInput
{
    private bool $nameProvided = false;
    private ?string $name = null;
    private bool $descriptionProvided = false;
    private ?string $description = null;
    private bool $operatorProvided = false;
    private ?string $operator = null;
    private bool $expectedValueProvided = false;
    private ?string $expectedValue = null;
    private bool $recoveryThresholdProvided = false;
    private ?string $recoveryThreshold = null;
    private bool $severityProvided = false;
    private ?string $severity = null;
    private bool $impactTypeProvided = false;
    private ?string $impactType = null;
    private bool $evaluationWindowSecondsProvided = false;
    private ?int $evaluationWindowSeconds = null;
    private bool $requiredOccurrencesProvided = false;
    private ?int $requiredOccurrences = null;
    private bool $isEnabledProvided = false;
    private ?bool $isEnabled = null;
    private bool $monitoringTemplateIdsProvided = false;
    /** @var list<string> */
    private array $monitoringTemplateIds = [];
    private bool $nodeGroupIdsProvided = false;
    /** @var list<string> */
    private array $nodeGroupIds = [];
    private bool $nodeIdsProvided = false;
    /** @var list<string> */
    private array $nodeIds = [];

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
    #[Assert\Choice(choices: AlertOperator::VALUES)]
    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(?string $operator): void
    {
        $this->operatorProvided = true;
        $this->operator = $operator;
    }

    #[Ignore]
    public function isOperatorProvided(): bool
    {
        return $this->operatorProvided;
    }

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(max: 255)]
    public function getExpectedValue(): ?string
    {
        return $this->expectedValue;
    }

    public function setExpectedValue(?string $expectedValue): void
    {
        $this->expectedValueProvided = true;
        $this->expectedValue = $expectedValue;
    }

    #[Ignore]
    public function isExpectedValueProvided(): bool
    {
        return $this->expectedValueProvided;
    }

    #[Assert\Length(max: 255)]
    public function getRecoveryThreshold(): ?string
    {
        return $this->recoveryThreshold;
    }

    public function setRecoveryThreshold(?string $recoveryThreshold): void
    {
        $this->recoveryThresholdProvided = true;
        $this->recoveryThreshold = $recoveryThreshold;
    }

    #[Ignore]
    public function isRecoveryThresholdProvided(): bool
    {
        return $this->recoveryThresholdProvided;
    }

    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Choice(choices: AlertSeverity::VALUES)]
    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(?string $severity): void
    {
        $this->severityProvided = true;
        $this->severity = $severity;
    }

    #[Ignore]
    public function isSeverityProvided(): bool
    {
        return $this->severityProvided;
    }

    /** impactType may be omitted on PATCH to preserve the currently assigned classification; the caller must always choose explicitly when providing it. */
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Choice(choices: AlertRuleImpactType::VALUES)]
    public function getImpactType(): ?string
    {
        return $this->impactType;
    }

    public function setImpactType(?string $impactType): void
    {
        $this->impactTypeProvided = true;
        $this->impactType = $impactType;
    }

    #[Ignore]
    public function isImpactTypeProvided(): bool
    {
        return $this->impactTypeProvided;
    }

    #[Assert\Positive]
    public function getEvaluationWindowSeconds(): ?int
    {
        return $this->evaluationWindowSeconds;
    }

    public function setEvaluationWindowSeconds(?int $evaluationWindowSeconds): void
    {
        $this->evaluationWindowSecondsProvided = true;
        $this->evaluationWindowSeconds = $evaluationWindowSeconds;
    }

    #[Ignore]
    public function isEvaluationWindowSecondsProvided(): bool
    {
        return $this->evaluationWindowSecondsProvided;
    }

    #[Assert\Positive]
    public function getRequiredOccurrences(): ?int
    {
        return $this->requiredOccurrences;
    }

    public function setRequiredOccurrences(?int $requiredOccurrences): void
    {
        $this->requiredOccurrencesProvided = true;
        $this->requiredOccurrences = $requiredOccurrences;
    }

    #[Ignore]
    public function isRequiredOccurrencesProvided(): bool
    {
        return $this->requiredOccurrencesProvided;
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
    #[Assert\Count(max: 200)]
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

    /** @return list<string> */
    #[Assert\Count(max: 200)]
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

    /** @return list<string> */
    #[Assert\Count(max: 200)]
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
}
