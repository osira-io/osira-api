<?php

declare(strict_types=1);

namespace App\Dto\Alert;

use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateAlertRuleInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Ulid]
    public string $itemDefinitionId = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: AlertOperator::VALUES)]
    public string $operator = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $expectedValue = '';

    #[Assert\Length(max: 255)]
    public ?string $recoveryThreshold = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: AlertSeverity::VALUES)]
    public string $severity = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: AlertRuleImpactType::VALUES)]
    public string $impactType = '';

    #[Assert\Positive]
    public int $evaluationWindowSeconds = 60;

    #[Assert\Positive]
    public int $requiredOccurrences = 1;

    public bool $isEnabled = true;

    /** @var list<string> */
    #[Assert\Count(max: 200)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public array $monitoringTemplateIds = [];

    /** @var list<string> */
    #[Assert\Count(max: 200)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public array $nodeGroupIds = [];

    /** @var list<string> */
    #[Assert\Count(max: 200)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public array $nodeIds = [];
}
