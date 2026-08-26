<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreateNotificationChannelInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    #[Assert\Choice(choices: ['email', 'webhook'])]
    public string $type = '';

    public bool $isEnabled = true;

    /** @var list<string> */
    #[Assert\Count(max: 100)]
    #[Assert\All([new Assert\Email()])]
    public array $emailRecipients = [];

    #[Assert\Length(max: 2048)]
    #[Assert\Url(protocols: ['http', 'https'])]
    public ?string $webhookUrl = null;

    #[Assert\Length(min: 16, max: 512)]
    public ?string $webhookSecret = null;

    #[Assert\Callback]
    public function validateConfiguration(ExecutionContextInterface $context): void
    {
        if ('email' === $this->type && [] === $this->emailRecipients) {
            $context->buildViolation('At least one email recipient is required.')->atPath('emailRecipients')->addViolation();
        }
        if ('webhook' === $this->type && null === $this->webhookUrl) {
            $context->buildViolation('A webhook URL is required.')->atPath('webhookUrl')->addViolation();
        }
    }
}
