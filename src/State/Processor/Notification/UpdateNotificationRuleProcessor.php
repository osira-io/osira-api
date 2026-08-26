<?php

declare(strict_types=1);

namespace App\State\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Notification\NotificationRuleOutput;
use App\Dto\Notification\UpdateNotificationRuleInput;
use App\Service\Notification\NotificationRuleManager;
use App\Service\Notification\NotificationRuleOutputFactory;

/** @implements ProcessorInterface<UpdateNotificationRuleInput, NotificationRuleOutput> */
final readonly class UpdateNotificationRuleProcessor implements ProcessorInterface
{
    public function __construct(private NotificationRuleManager $manager, private NotificationRuleOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationRuleOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
