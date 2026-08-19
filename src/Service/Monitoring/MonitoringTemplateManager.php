<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Dto\Monitoring\CreateMonitoringTemplateInput;
use App\Dto\Monitoring\UpdateMonitoringTemplateInput;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Repository\Monitoring\ItemDefinitionRepository;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Ulid;

final readonly class MonitoringTemplateManager
{
    public function __construct(
        private MonitoringTemplateRepository $repository,
        private ItemDefinitionRepository $itemDefinitionRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function create(CreateMonitoringTemplateInput $input): MonitoringTemplate
    {
        $name = self::normalizeName($input->name);
        $slug = self::normalizeSlug($input->slug ?? $name);
        $this->assertAvailable($name, $slug);
        $now = $this->clock->now();
        $monitoringTemplate = new MonitoringTemplate($name, $slug, self::normalizeDescription($input->description), false, $input->isEnabled, $now);
        $monitoringTemplate->replaceItemDefinitions($this->resolveItemDefinitions($input->itemDefinitionIds), $now);
        $this->entityManager->persist($monitoringTemplate);
        $this->entityManager->flush();

        return $monitoringTemplate;
    }

    public function update(string $id, UpdateMonitoringTemplateInput $input): MonitoringTemplate
    {
        $monitoringTemplate = $this->find($id);
        if ($monitoringTemplate->isSystem()) {
            throw new ConflictHttpException('System monitoring templates cannot be modified directly.');
        }

        $name = $input->isNameProvided() ? self::normalizeName($input->getName() ?? '') : $monitoringTemplate->name();
        $slug = $input->isSlugProvided() ? self::normalizeSlug($input->getSlug() ?? $name) : $monitoringTemplate->slug();
        $description = $input->isDescriptionProvided() ? self::normalizeDescription($input->getDescription()) : $monitoringTemplate->description();
        $isEnabled = $input->isIsEnabledProvided() ? (bool) $input->getIsEnabled() : $monitoringTemplate->isEnabled();
        $this->assertAvailable($name, $slug, $monitoringTemplate);
        $now = $this->clock->now();
        $monitoringTemplate->update($name, $slug, $description, $isEnabled, $now);

        if ($input->areItemDefinitionIdsProvided()) {
            $monitoringTemplate->replaceItemDefinitions($this->resolveItemDefinitions($input->getItemDefinitionIds()), $now);
        }

        $this->entityManager->flush();

        return $monitoringTemplate;
    }

    public function delete(string $id): void
    {
        $monitoringTemplate = $this->find($id);
        if ($monitoringTemplate->isSystem()) {
            throw new ConflictHttpException('System monitoring templates cannot be deleted.');
        }
        $this->entityManager->remove($monitoringTemplate);
        $this->entityManager->flush();
    }

    private function find(string $id): MonitoringTemplate
    {
        if (!Ulid::isValid($id)) {
            throw new NotFoundHttpException('Monitoring template not found.');
        }
        $monitoringTemplate = $this->repository->find(new Ulid($id));
        if (!$monitoringTemplate instanceof MonitoringTemplate) {
            throw new NotFoundHttpException('Monitoring template not found.');
        }

        return $monitoringTemplate;
    }

    private function assertAvailable(string $name, string $slug, ?MonitoringTemplate $current = null): void
    {
        foreach ([['name' => $name], ['slug' => $slug]] as $criteria) {
            $existing = $this->repository->findOneBy($criteria);
            if ($existing instanceof MonitoringTemplate && $existing !== $current) {
                throw new ConflictHttpException('A monitoring template with this name or slug already exists.');
            }
        }
    }

    /** @param list<string> $ids
     * @return list<ItemDefinition>
     */
    private function resolveItemDefinitions(array $ids): array
    {
        $items = [];
        foreach (array_values(array_unique($ids)) as $id) {
            if (!Ulid::isValid($id)) {
                throw new UnprocessableEntityHttpException(\sprintf('Item definition "%s" does not exist.', $id));
            }
            $itemDefinition = $this->itemDefinitionRepository->find(new Ulid($id));
            if (!$itemDefinition instanceof ItemDefinition) {
                throw new UnprocessableEntityHttpException(\sprintf('Item definition "%s" does not exist.', $id));
            }
            $items[] = $itemDefinition;
        }

        return $items;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new UnprocessableEntityHttpException('A monitoring template name cannot be empty.');
        }

        return $name;
    }

    private static function normalizeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ('' === $slug) {
            throw new UnprocessableEntityHttpException('A monitoring template slug cannot be empty.');
        }

        return $slug;
    }

    private static function normalizeDescription(?string $description): ?string
    {
        if (null === $description) {
            return null;
        }
        $description = trim($description);

        return '' === $description ? null : $description;
    }
}
