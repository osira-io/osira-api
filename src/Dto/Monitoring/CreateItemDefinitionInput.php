<?php

declare(strict_types=1);

namespace App\Dto\Monitoring;

use App\Entity\Monitoring\ItemValueType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreateItemDefinitionInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $key = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\Length(max: 32)]
    public ?string $unit = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [ItemValueType::class, 'metricValues'])]
    public string $valueType = '';

    #[Assert\Positive]
    public int $intervalSeconds = 60;

    #[Assert\Positive]
    public ?int $timeoutSeconds = null;

    #[Assert\Length(max: 20000)]
    #[Assert\Regex(pattern: '/\x00/', match: false, message: 'Collection commands cannot contain NUL bytes.')]
    public ?string $linuxCommand = null;

    #[Assert\Length(max: 20000)]
    #[Assert\Regex(pattern: '/\x00/', match: false, message: 'Collection commands cannot contain NUL bytes.')]
    public ?string $windowsCommand = null;

    public bool $isEnabled = true;

    #[Assert\Callback]
    public function validateCommands(ExecutionContextInterface $context): void
    {
        if (null === $this->linuxCommand && null === $this->windowsCommand) {
            $context->buildViolation('At least one Linux or Windows collection command must be provided.')->atPath('linuxCommand')->addViolation();
        }
        foreach (['linuxCommand', 'windowsCommand'] as $property) {
            $command = $this->{$property};
            if (null !== $command && '' === trim($command)) {
                $context->buildViolation('A collection command cannot be empty when provided.')->atPath($property)->addViolation();
            }
        }
    }
}
