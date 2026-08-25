<?php

declare(strict_types=1);

namespace App\Dto\Maintenance;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreateMaintenanceWindowInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/(?:Z|[+-]\d{2}:\d{2})$/', message: 'startsAt must include an explicit timezone offset.')]
    public string $startsAt = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/(?:Z|[+-]\d{2}:\d{2})$/', message: 'endsAt must include an explicit timezone offset.')]
    public string $endsAt = '';

    public bool $isEnabled = true;

    /** @var list<string> */
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public array $nodeIds = [];

    /** @var list<string> */
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Type('string'), new Assert\Ulid()])]
    public array $nodeGroupIds = [];

    #[Assert\Callback]
    public function validateTargets(ExecutionContextInterface $context): void
    {
        if ([] === $this->nodeIds && [] === $this->nodeGroupIds) {
            $context->buildViolation('At least one node or node group target must be provided.')->atPath('nodeIds')->addViolation();
        }
    }
}
