<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Dto\Monitoring\CreateMonitoringTemplateInput;
use App\Dto\Monitoring\UpdateMonitoringTemplateInput;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Repository\Monitoring\ItemDefinitionRepository;
use App\Repository\Monitoring\MonitoringTemplateRepository;
use App\Service\Monitoring\Factory\MonitoringTemplateFactory;
use App\Service\Shared\Exception\ResourceConflictException;
use App\Service\Shared\Exception\ResourceNotFoundException;
use App\Service\Shared\Exception\ResourceValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class MonitoringTemplateManager
{
    public function __construct(
        private MonitoringTemplateRepository $repository,
        private ItemDefinitionRepository $itemDefinitionRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private MonitoringTemplateFactory $monitoringTemplateFactory,
    ) {
    }

    public function create(CreateMonitoringTemplateInput $input): MonitoringTemplate
    {
        $name = $this->monitoringTemplateFactory->normalizeName($input->name);
        $slug = $this->monitoringTemplateFactory->normalizeSlug($input->slug ?? $name);
        $this->assertAvailable($name, $slug);
        $now = $this->clock->now();
        $monitoringTemplate = $this->monitoringTemplateFactory->create($name, $slug, $input->description, false, $input->isEnabled, $now);
        $monitoringTemplate->replaceItemDefinitions($this->resolveItemDefinitions($input->itemDefinitionIds), $now);
        $this->entityManager->persist($monitoringTemplate);
        $this->entityManager->flush();

        return $monitoringTemplate;
    }

    public function update(string $id, UpdateMonitoringTemplateInput $input): MonitoringTemplate
    {
        $monitoringTemplate = $this->find($id);
        if ($monitoringTemplate->isSystem()) {
            throw new ResourceConflictException('System monitoring templates cannot be modified directly.');
        }

        $name = $input->isNameProvided() ? $this->monitoringTemplateFactory->normalizeName($input->getName() ?? '') : $monitoringTemplate->name();
        $slug = $input->isSlugProvided() ? $this->monitoringTemplateFactory->normalizeSlug($input->getSlug() ?? $name) : $monitoringTemplate->slug();
        $description = $input->isDescriptionProvided() ? $this->monitoringTemplateFactory->normalizeDescription($input->getDescription()) : $monitoringTemplate->description();
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
            throw new ResourceConflictException('System monitoring templates cannot be deleted.');
        }
        $this->entityManager->remove($monitoringTemplate);
        $this->entityManager->flush();
    }

    private function find(string $id): MonitoringTemplate
    {
        if (!Ulid::isValid($id)) {
            throw new ResourceNotFoundException('Monitoring template not found.');
        }
        $monitoringTemplate = $this->repository->find(new Ulid($id));
        if (!$monitoringTemplate instanceof MonitoringTemplate) {
            throw new ResourceNotFoundException('Monitoring template not found.');
        }

        return $monitoringTemplate;
    }

    private function assertAvailable(string $name, string $slug, ?MonitoringTemplate $current = null): void
    {
        foreach ([['name' => $name], ['slug' => $slug]] as $criteria) {
            $existing = $this->repository->findOneBy($criteria);
            if ($existing instanceof MonitoringTemplate && $existing !== $current) {
                throw new ResourceConflictException('A monitoring template with this name or slug already exists.');
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
                throw new ResourceValidationException(\sprintf('Item definition "%s" does not exist.', $id));
            }
            $itemDefinition = $this->itemDefinitionRepository->find(new Ulid($id));
            if (!$itemDefinition instanceof ItemDefinition) {
                throw new ResourceValidationException(\sprintf('Item definition "%s" does not exist.', $id));
            }
            $items[] = $itemDefinition;
        }

        return $items;
    }
}
