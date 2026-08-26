<?php

declare(strict_types=1);

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Notification\NotificationRuleOutput;
use App\Service\Notification\NotificationRuleManager;
use App\Service\Notification\NotificationRuleOutputFactory;

/** @implements ProviderInterface<NotificationRuleOutput> */
final readonly class NotificationRuleProvider implements ProviderInterface
{
    public function __construct(private NotificationRuleManager $manager, private NotificationRuleOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationRuleOutput
    {
        $id = $uriVariables['id'] ?? '';

        return $this->outputFactory->create($this->manager->find(\is_string($id) ? $id : ''));
    }
}
