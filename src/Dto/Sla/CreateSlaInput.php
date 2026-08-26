<?php

declare(strict_types=1);

namespace App\Dto\Sla;

use App\Entity\Sla\SlaPeriodType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreateSlaInput
{
    #[Assert\NotBlank] #[Assert\Length(max: 128)] public string $name = '';
    #[Assert\Length(max: 2000)] public ?string $description = null;
    #[Assert\Range(min: 0, max: 100)] public float $targetPercentage = 99.9;
    #[Assert\Choice(choices: SlaPeriodType::VALUES)] public string $periodType = 'rolling_30_days';
    public bool $excludeMaintenance = true;
    public bool $isEnabled = true;
    /** @var list<string> */ #[Assert\Count(max: 100)] #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])] public array $nodeIds = [];
    /** @var list<string> */ #[Assert\Count(max: 100)] #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])] public array $nodeGroupIds = [];
    #[Assert\Callback]
    public function validateScope(ExecutionContextInterface $context): void
    {
        if ([] === $this->nodeIds && [] === $this->nodeGroupIds) {
            $context->buildViolation('At least one node or node group scope must be provided.')->atPath('nodeIds')->addViolation();
        }
    }
}
