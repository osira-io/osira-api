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
use App\Dto\Node\NodeSummary;
use App\Dto\NodeGroup\NodeGroupSummary;
use App\Security\Rbac\PermissionCode;
use App\State\Processor\Notification\CreateNotificationRuleProcessor;
use App\State\Processor\Notification\DeleteNotificationRuleProcessor;
use App\State\Processor\Notification\UpdateNotificationRuleProcessor;
use App\State\Provider\Notification\NotificationRuleProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(shortName: 'NotificationRule', operations: [
    new Get(uriTemplate: '/notification-rules/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::NOTIFICATION_RULES_READ."')", provider: NotificationRuleProvider::class, openapi: new OpenApiOperation(tags: ['NotificationRule'], summary: 'Gets a notification routing rule.', security: [['JWT' => []]])),
    new Post(uriTemplate: '/notification-rules', status: Response::HTTP_CREATED, security: "is_granted('".PermissionCode::NOTIFICATION_RULES_CREATE."')", input: CreateNotificationRuleInput::class, output: self::class, read: false, processor: CreateNotificationRuleProcessor::class, openapi: new OpenApiOperation(tags: ['NotificationRule'], summary: 'Creates a notification routing rule.', security: [['JWT' => []]])),
    new Patch(uriTemplate: '/notification-rules/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::NOTIFICATION_RULES_UPDATE."')", input: UpdateNotificationRuleInput::class, output: self::class, read: false, processor: UpdateNotificationRuleProcessor::class, openapi: new OpenApiOperation(tags: ['NotificationRule'], summary: 'Updates a notification routing rule.', security: [['JWT' => []]])),
    new Delete(uriTemplate: '/notification-rules/{id}', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], security: "is_granted('".PermissionCode::NOTIFICATION_RULES_DELETE."')", read: false, processor: DeleteNotificationRuleProcessor::class, openapi: new OpenApiOperation(tags: ['NotificationRule'], summary: 'Deletes a notification routing rule.', security: [['JWT' => []]])),
])]
final readonly class NotificationRuleOutput
{
    /** @param list<string> $severities
     * @param list<NotificationChannelOutput> $channels
     * @param list<NodeSummary> $nodes
     * @param list<NodeGroupSummary> $nodeGroups
     */
    public function __construct(
        #[ApiProperty(identifier: true)] public string $id,
        public string $name,
        public bool $isEnabled,
        public array $severities,
        public array $channels,
        public array $nodes,
        public array $nodeGroups,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
