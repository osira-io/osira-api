<?php

declare(strict_types=1);

namespace App\Service\Shared;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Tag;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'api_platform.openapi.factory')]
final readonly class TagOrderOpenApiFactory implements OpenApiFactoryInterface
{
    /**
     * Explicit, stable Swagger UI grouping order. API Platform otherwise computes the tag
     * list from resource discovery order, which varies with the filesystem scan order of
     * `api_platform.mapping.paths` and is not deterministic across environments.
     *
     * @var list<string>
     */
    private const array TAG_ORDER = [
        'CurrentUser',
        'User',
        'Role',
        'Permission',
        'Node',
        'NodeGroup',
        'MonitoringTemplate',
        'ItemDefinition',
        'EnrollmentToken',
        'AgentEnrollment',
        'Audit',
    ];

    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $tagsByName = [];
        foreach ($openApi->getTags() as $tag) {
            if (!$tag instanceof Tag) {
                continue;
            }
            $tagsByName[$tag->getName()] = $tag;
        }

        $ordered = [];
        foreach (self::TAG_ORDER as $name) {
            if (isset($tagsByName[$name])) {
                $ordered[] = $tagsByName[$name];
                unset($tagsByName[$name]);
            }
        }

        // Any tag not explicitly listed keeps the document valid instead of silently disappearing.
        return $openApi->withTags([...$ordered, ...array_values($tagsByName)]);
    }
}
