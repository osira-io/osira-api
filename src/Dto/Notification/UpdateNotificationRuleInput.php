<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateNotificationRuleInput
{
    #[Assert\Length(min: 1, max: 128)] public ?string $name = null;
    public ?bool $isEnabled = null;
    /** @var list<string>|null */
    #[Assert\Count(min: 1, max: 4)] #[Assert\All([new Assert\Choice(choices: ['info', 'warning', 'critical'])])] public ?array $severities = null;
    /** @var list<string>|null */
    #[Assert\Count(min: 1, max: 100)] #[Assert\All([new Assert\Ulid()])] public ?array $channelIds = null;
    /** @var list<string>|null */
    #[Assert\Count(max: 100)] #[Assert\All([new Assert\Ulid()])] public ?array $nodeIds = null;
    /** @var list<string>|null */
    #[Assert\Count(max: 100)] #[Assert\All([new Assert\Ulid()])] public ?array $nodeGroupIds = null;
}
