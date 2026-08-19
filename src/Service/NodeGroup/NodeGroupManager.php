<?php

declare(strict_types=1);

namespace App\Service\NodeGroup;

use App\Dto\NodeGroup\CreateNodeGroupInput;
use App\Dto\NodeGroup\UpdateNodeGroupInput;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Repository\NodeGroup\NodeGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Ulid;

final readonly class NodeGroupManager
{
    public function __construct(
        private NodeGroupRepository $repository,
        private MonitoringTemplateRepository $monitoringTemplateRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function create(CreateNodeGroupInput $input): NodeGroup
    {
        $name = self::normalizeName($input->name);
        $this->assertNameAvailable($name);
        $now = $this->clock->now();
        $group = new NodeGroup($name, self::normalizeDescription($input->description), $now);
        $group->replaceMonitoringTemplates($this->resolveMonitoringTemplates($input->monitoringTemplateIds), $now);
        $this->entityManager->persist($group);
        $this->entityManager->flush();

        return $group;
    }

    public function update(string $id, UpdateNodeGroupInput $input): NodeGroup
    {
        $group = $this->find($id);
        $name = $input->isNameProvided() ? self::normalizeName($input->getName()) : $group->name();
        $description = $input->isDescriptionProvided()
            ? self::normalizeDescription($input->getDescription())
            : $group->description();

        $this->assertNameAvailable($name, $group);
        $now = $this->clock->now();
        $group->update($name, $description, $now);
        if ($input->areMonitoringTemplateIdsProvided()) {
            $group->replaceMonitoringTemplates($this->resolveMonitoringTemplates($input->getMonitoringTemplateIds()), $now);
        }
        $this->entityManager->flush();

        return $group;
    }

    public function delete(string $id): void
    {
        $this->entityManager->remove($this->find($id));
        $this->entityManager->flush();
    }

    private function find(string $id): NodeGroup
    {
        if (!Ulid::isValid($id)) {
            throw new NotFoundHttpException('Node group not found.');
        }
        $group = $this->repository->find(new Ulid($id));
        if (!$group instanceof NodeGroup) {
            throw new NotFoundHttpException('Node group not found.');
        }

        return $group;
    }

    private function assertNameAvailable(string $name, ?NodeGroup $current = null): void
    {
        $existing = $this->repository->findOneBy(['name' => $name]);
        if ($existing instanceof NodeGroup && $existing !== $current) {
            throw new ConflictHttpException('A node group with this name already exists.');
        }
    }

    private static function normalizeName(?string $name): string
    {
        $name = trim($name ?? '');
        if ('' === $name) {
            throw new UnprocessableEntityHttpException('A node group name cannot be empty.');
        }

        return $name;
    }

    private static function normalizeDescription(?string $description): ?string
    {
        if (null === $description) {
            return null;
        }
        $description = trim($description);

        return '' === $description ? null : $description;
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
