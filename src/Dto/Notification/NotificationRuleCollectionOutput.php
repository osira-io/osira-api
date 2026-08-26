<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Dto\Shared\PaginationMetadata;
use App\Security\Rbac\PermissionCode;
use App\State\Provider\Notification\NotificationRuleCollectionProvider;

#[ApiResource(shortName: 'NotificationRule', operations: [new GetCollection(uriTemplate: '/notification-rules', security: "is_granted('".PermissionCode::NOTIFICATION_RULES_READ."')", provider: NotificationRuleCollectionProvider::class, openapi: new OpenApiOperation(tags: ['NotificationRule'], summary: 'Lists notification routing rules.', security: [['JWT' => []]]))])]
final readonly class NotificationRuleCollectionOutput
{
    /** @param list<NotificationRuleOutput> $items */
    public function __construct(public array $items, public PaginationMetadata $pagination)
    {
    }
}
