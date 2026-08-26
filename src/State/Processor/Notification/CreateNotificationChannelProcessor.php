<?php

declare(strict_types=1);

namespace App\State\Processor\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Notification\CreateNotificationChannelInput;
use App\Dto\Notification\NotificationChannelOutput;
use App\Service\Notification\NotificationChannelManager;
use App\Service\Notification\NotificationChannelOutputFactory;

/** @implements ProcessorInterface<CreateNotificationChannelInput, NotificationChannelOutput> */
final readonly class CreateNotificationChannelProcessor implements ProcessorInterface
{
    public function __construct(private NotificationChannelManager $manager, private NotificationChannelOutputFactory $outputFactory)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NotificationChannelOutput
    {
        return $this->outputFactory->create($this->manager->create($data));
    }
}
