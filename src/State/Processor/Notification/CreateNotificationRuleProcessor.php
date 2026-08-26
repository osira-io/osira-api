<?php

declare(strict_types=1);

namespace App\State\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Notification\CreateNotificationRuleInput;
use App\Dto\Notification\NotificationRuleOutput;
use App\Service\Notification\NotificationRuleManager;
use App\Service\Notification\NotificationRuleOutputFactory;

/** @implements ProcessorInterface<CreateNotificationRuleInput, NotificationRuleOutput> */
final readonly class CreateNotificationRuleProcessor implements ProcessorInterface
{
    public function __construct(private NotificationRuleManager $manager, private NotificationRuleOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationRuleOutput
    {
        return $this->outputFactory->create($this->manager->create($data));
    }
}
