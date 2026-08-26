<?php

declare(strict_types=1);

namespace App\State\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Notification\NotificationChannelOutput;
use App\Dto\Notification\UpdateNotificationChannelInput;
use App\Service\Notification\NotificationChannelManager;
use App\Service\Notification\NotificationChannelOutputFactory;

/** @implements ProcessorInterface<UpdateNotificationChannelInput, NotificationChannelOutput> */
final readonly class UpdateNotificationChannelProcessor implements ProcessorInterface
{
    public function __construct(private NotificationChannelManager $manager, private NotificationChannelOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationChannelOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->update(\is_string($id) ? $id : '', $data));
    }
}
