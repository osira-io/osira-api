<?php

declare(strict_types=1);

namespace App\Service\Node;

use App\Dto\Node\UpdateNodeInput;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Repository\Node\NodeRepository;
use App\Repository\NodeGroup\NodeGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Ulid;

final readonly class NodeUpdater
{
    public function __construct(
        private NodeRepository $nodeRepository,
        private NodeGroupRepository $nodeGroupRepository,
        private MonitoringTemplateRepository $monitoringTemplateRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function update(string $id, UpdateNodeInput $input): Node
    {
        if (!Ulid::isValid($id)) {
            throw new NotFoundHttpException('Node not found.');
        }

        $node = $this->nodeRepository->find(new Ulid($id));
        if (!$node instanceof Node) {
            throw new NotFoundHttpException('Node not found.');
        }

        $displayName = $input->isDisplayNameProvided()
            ? self::normalizeNullable($input->getDisplayName())
            : $node->displayName();
        $environment = $input->isEnvironmentProvided()
            ? self::normalizeEnvironment($input->getEnvironment())
            : $node->environment();
        $tags = $input->areTagsProvided() ? self::normalizeTags($input->getTags()) : $node->tags();

        $node->updateBusinessProperties($displayName, $environment, $tags);
        if ($input->areGroupsProvided()) {
            $node->replaceGroups($this->resolveGroups($input->getGroups()));
        }
        if ($input->areMonitoringTemplateIdsProvided()) {
            $node->replaceMonitoringTemplates($this->resolveMonitoringTemplates($input->getMonitoringTemplateIds()));
        }

        $this->entityManager->flush();

        return $node;
    }

    private static function normalizeNullable(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }

    private static function normalizeEnvironment(?string $environment): ?string
    {
        $normalized = self::normalizeNullable($environment);
        if (null !== $normalized && 1 !== preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $normalized)) {
            throw new UnprocessableEntityHttpException('The environment contains invalid characters.');
        }

        return null === $normalized ? null : mb_strtolower($normalized);
    }

    /** @param list<string> $tags
     * @return list<string>
     */
    private static function normalizeTags(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ('' === $tag) {
                throw new UnprocessableEntityHttpException('Tags cannot contain empty values.');
            }
            if (mb_strlen($tag) > 64) {
                throw new UnprocessableEntityHttpException('Tags cannot exceed 64 characters.');
            }
            if (!\in_array($tag, $normalized, true)) {
                $normalized[] = $tag;
            }
        }

        if (\count($normalized) > 50) {
            throw new UnprocessableEntityHttpException('A node cannot have more than 50 tags.');
        }

        return $normalized;
    }

    /** @param list<string> $ids
     * @return list<NodeGroup>
     */
    private function resolveGroups(array $ids): array
    {
        $uniqueIds = array_values(array_unique($ids));
        $groups = [];
        foreach ($uniqueIds as $id) {
            if (!Ulid::isValid($id)) {
                throw new UnprocessableEntityHttpException(\sprintf('Node group "%s" does not exist.', $id));
            }

            $group = $this->nodeGroupRepository->find(new Ulid($id));
            if (!$group instanceof NodeGroup) {
                throw new UnprocessableEntityHttpException(\sprintf('Node group "%s" does not exist.', $id));
            }
            $groups[] = $group;
        }

        return $groups;
    }

    /** @param list<string> $ids
     * @return list<MonitoringTemplate>
     */
    private function resolveMonitoringTemplates(array $ids): array
    {
        $monitoringTemplates = [];
        foreach (array_values(array_unique($ids)) as $id) {
            if (!Ulid::isValid($id)) {
                throw new UnprocessableEntityHttpException(\sprintf('Monitoring template "%s" does not exist.', $id));
            }

            $monitoringTemplate = $this->monitoringTemplateRepository->find(new Ulid($id));
            if (!$monitoringTemplate instanceof MonitoringTemplate) {
                throw new UnprocessableEntityHttpException(\sprintf('Monitoring template "%s" does not exist.', $id));
            }
            $monitoringTemplates[] = $monitoringTemplate;
        }

        return $monitoringTemplates;
    }
}
