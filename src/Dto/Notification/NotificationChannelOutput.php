<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Notification\CreateNotificationChannelProcessor;
use App\State\Processor\Notification\DeleteNotificationChannelProcessor;
use App\State\Processor\Notification\UpdateNotificationChannelProcessor;
use App\State\Provider\Notification\NotificationChannelProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(shortName: 'NotificationChannel', operations: [
    new Get(uriTemplate: '/notification-channels/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::NOTIFICATION_CHANNELS_READ."')", provider: NotificationChannelProvider::class, openapi: new OpenApiOperation(tags: ['NotificationChannel'], summary: 'Gets a notification channel.', security: [['JWT' => []]])),
    new Post(uriTemplate: '/notification-channels', status: Response::HTTP_CREATED, security: "is_granted('".PermissionCode::NOTIFICATION_CHANNELS_CREATE."')", input: CreateNotificationChannelInput::class, output: self::class, read: false, processor: CreateNotificationChannelProcessor::class, openapi: new OpenApiOperation(tags: ['NotificationChannel'], summary: 'Creates an email or webhook notification channel.', description: 'Webhook secrets are write-only and are never returned.', security: [['JWT' => []]])),
    new Patch(uriTemplate: '/notification-channels/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::NOTIFICATION_CHANNELS_UPDATE."')", input: UpdateNotificationChannelInput::class, output: self::class, read: false, processor: UpdateNotificationChannelProcessor::class, openapi: new OpenApiOperation(tags: ['NotificationChannel'], summary: 'Updates a notification channel.', security: [['JWT' => []]])),
    new Delete(uriTemplate: '/notification-channels/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::NOTIFICATION_CHANNELS_DELETE."')", read: false, processor: DeleteNotificationChannelProcessor::class, openapi: new OpenApiOperation(tags: ['NotificationChannel'], summary: 'Deletes a notification channel.', security: [['JWT' => []]])),
])]
final readonly class NotificationChannelOutput
{
    /** @param list<string> $emailRecipients */
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public string $name,
        public string $type,
        public bool $isEnabled,
        public array $emailRecipients,
        public ?string $webhookUrl,
        public bool $hasWebhookSecret,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
