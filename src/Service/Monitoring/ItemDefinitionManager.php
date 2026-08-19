<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Dto\Monitoring\CreateItemDefinitionInput;
use App\Dto\Monitoring\UpdateItemDefinitionInput;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Repository\Monitoring\ItemDefinitionRepository;
use App\Factory\Monitoring\ItemDefinitionFactory;
use App\Service\Shared\Exception\ResourceConflictException;
use App\Service\Shared\Exception\ResourceNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Ulid;

final readonly class ItemDefinitionManager
{
    public function __construct(
        private ItemDefinitionRepository $repository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private ItemDefinitionFactory $itemDefinitionFactory,
    ) {
    }

    public function create(CreateItemDefinitionInput $input): ItemDefinition
    {
        $key = $this->itemDefinitionFactory->normalizeKey($input->key);
        $this->assertKeyAvailable($key);
        $itemDefinition = $this->itemDefinitionFactory->create(
            $key,
            $input->name,
            $input->description,
            $input->category,
            $input->unit,
            ItemValueType::from($input->valueType),
            $input->intervalSeconds,
            $input->timeoutSeconds,
            false,
            $input->isEnabled,
            $this->clock->now(),
        );
        $this->entityManager->persist($itemDefinition);
        $this->entityManager->flush();

        return $itemDefinition;
    }

    public function update(string $id, UpdateItemDefinitionInput $input): ItemDefinition
    {
        $itemDefinition = $this->find($id);
        if ($itemDefinition->isSystem()) {
            throw new ResourceConflictException('System item definitions cannot be modified directly.');
        }

        $key = $input->isKeyProvided() ? $this->itemDefinitionFactory->normalizeKey($input->getKey() ?? '') : $itemDefinition->key();
        $name = $input->isNameProvided() ? $this->itemDefinitionFactory->normalizeName($input->getName() ?? '') : $itemDefinition->name();
        $description = $input->isDescriptionProvided() ? $this->itemDefinitionFactory->normalizeNullable($input->getDescription()) : $itemDefinition->description();
        $category = $input->isCategoryProvided() ? $this->itemDefinitionFactory->normalizeNullable($input->getCategory()) : $itemDefinition->category();
        $unit = $input->isUnitProvided() ? $this->itemDefinitionFactory->normalizeNullable($input->getUnit()) : $itemDefinition->unit();
        $valueType = $input->isValueTypeProvided() ? ItemValueType::from((string) $input->getValueType()) : $itemDefinition->valueType();
        $intervalSeconds = $input->isIntervalSecondsProvided() ? $this->itemDefinitionFactory->normalizePositive($input->getIntervalSeconds() ?? 0, 'The interval must be greater than zero.') : $itemDefinition->intervalSeconds();
        $timeoutSeconds = $input->isTimeoutSecondsProvided() ? $this->itemDefinitionFactory->normalizeNullablePositive($input->getTimeoutSeconds(), 'The timeout must be greater than zero when provided.') : $itemDefinition->timeoutSeconds();
        $isEnabled = $input->isIsEnabledProvided() ? (bool) $input->getIsEnabled() : $itemDefinition->isEnabled();

        $this->assertKeyAvailable($key, $itemDefinition);
        $itemDefinition->update($key, $name, $description, $category, $unit, $valueType, $intervalSeconds, $timeoutSeconds, $isEnabled, $this->clock->now());
        $this->entityManager->flush();

        return $itemDefinition;
    }

    public function delete(string $id): void
    {
        $itemDefinition = $this->find($id);
        if ($itemDefinition->isSystem()) {
            throw new ResourceConflictException('System item definitions cannot be deleted.');
        }
        $this->entityManager->remove($itemDefinition);
        $this->entityManager->flush();
    }

    private function find(string $id): ItemDefinition
    {
        if (!Ulid::isValid($id)) {
            throw new ResourceNotFoundException('Item definition not found.');
        }
        $itemDefinition = $this->repository->find(new Ulid($id));
        if (!$itemDefinition instanceof ItemDefinition) {
            throw new ResourceNotFoundException('Item definition not found.');
        }

        return $itemDefinition;
    }

    private function assertKeyAvailable(string $key, ?ItemDefinition $current = null): void
    {
        $existing = $this->repository->findOneBy(['key' => $key]);
        if ($existing instanceof ItemDefinition && $existing !== $current) {
            throw new ResourceConflictException('An item definition with this key already exists.');
        }
    }
}
