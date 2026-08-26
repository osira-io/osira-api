<?php

declare(strict_types=1);

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Notification\NotificationChannelOutput;
use App\Service\Notification\NotificationChannelManager;
use App\Service\Notification\NotificationChannelOutputFactory;

/** @implements ProviderInterface<NotificationChannelOutput> */
final readonly class NotificationChannelProvider implements ProviderInterface
{
    public function __construct(private NotificationChannelManager $manager, private NotificationChannelOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationChannelOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->find(\is_string($id) ? $id : ''));
    }
}
