<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateNotificationRuleInput
{
    #[Assert\NotBlank] #[Assert\Length(max: 128)] public string $name = '';
    public bool $isEnabled = true;
    /** @var list<string> */
    #[Assert\Count(min: 1, max: 4)] #[Assert\All([new Assert\Choice(choices: ['info', 'warning', 'critical'])])] public array $severities = [];
    /** @var list<string> */
    #[Assert\Count(min: 1, max: 100)] #[Assert\All([new Assert\Ulid()])] public array $channelIds = [];
    /** @var list<string> */
    #[Assert\Count(max: 100)] #[Assert\All([new Assert\Ulid()])] public array $nodeIds = [];
    /** @var list<string> */
    #[Assert\Count(max: 100)] #[Assert\All([new Assert\Ulid()])] public array $nodeGroupIds = [];
}
