<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Shared\PaginationMetadata;
use App\Security\Rbac\PermissionCode;
use App\State\Provider\Notification\NotificationChannelCollectionProvider;

#[ApiResource(shortName: 'NotificationChannel', operations: [new GetCollection(uriTemplate: '/notification-channels', security: "is_granted('".PermissionCode::NOTIFICATION_CHANNELS_READ."')", provider: NotificationChannelCollectionProvider::class, openapi: new OpenApiOperation(tags: ['NotificationChannel'], summary: 'Lists notification channels.', security: [['JWT' => []]]))])]
final readonly class NotificationChannelCollectionOutput
{
    /** @param list<NotificationChannelOutput> $items */
    public function __construct(public array $items, public PaginationMetadata $pagination)
    {
    }
}
