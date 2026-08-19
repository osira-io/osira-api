<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Dto\Monitoring\CreateItemDefinitionInput;
use App\Dto\Monitoring\UpdateItemDefinitionInput;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Repository\Monitoring\ItemDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Ulid;

final readonly class ItemDefinitionManager
{
    public function __construct(
        private ItemDefinitionRepository $repository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function create(CreateItemDefinitionInput $input): ItemDefinition
    {
        $key = self::normalizeKey($input->key);
        $this->assertKeyAvailable($key);
        $itemDefinition = new ItemDefinition(
            $key,
            self::normalizeName($input->name),
            self::normalizeNullable($input->description),
            self::normalizeNullable($input->category),
            self::normalizeNullable($input->unit),
            ItemValueType::from($input->valueType),
            self::normalizePositive($input->intervalSeconds, 'The interval must be greater than zero.'),
            self::normalizeNullablePositive($input->timeoutSeconds, 'The timeout must be greater than zero when provided.'),
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
            throw new ConflictHttpException('System item definitions cannot be modified directly.');
        }

        $key = $input->isKeyProvided() ? self::normalizeKey($input->getKey() ?? '') : $itemDefinition->key();
        $name = $input->isNameProvided() ? self::normalizeName($input->getName() ?? '') : $itemDefinition->name();
        $description = $input->isDescriptionProvided() ? self::normalizeNullable($input->getDescription()) : $itemDefinition->description();
        $category = $input->isCategoryProvided() ? self::normalizeNullable($input->getCategory()) : $itemDefinition->category();
        $unit = $input->isUnitProvided() ? self::normalizeNullable($input->getUnit()) : $itemDefinition->unit();
        $valueType = $input->isValueTypeProvided() ? ItemValueType::from((string) $input->getValueType()) : $itemDefinition->valueType();
        $intervalSeconds = $input->isIntervalSecondsProvided() ? self::normalizePositive($input->getIntervalSeconds() ?? 0, 'The interval must be greater than zero.') : $itemDefinition->intervalSeconds();
        $timeoutSeconds = $input->isTimeoutSecondsProvided() ? self::normalizeNullablePositive($input->getTimeoutSeconds(), 'The timeout must be greater than zero when provided.') : $itemDefinition->timeoutSeconds();
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
            throw new ConflictHttpException('System item definitions cannot be deleted.');
        }
        $this->entityManager->remove($itemDefinition);
        $this->entityManager->flush();
    }

    private function find(string $id): ItemDefinition
    {
        if (!Ulid::isValid($id)) {
            throw new NotFoundHttpException('Item definition not found.');
        }
        $itemDefinition = $this->repository->find(new Ulid($id));
        if (!$itemDefinition instanceof ItemDefinition) {
            throw new NotFoundHttpException('Item definition not found.');
        }

        return $itemDefinition;
    }

    private function assertKeyAvailable(string $key, ?ItemDefinition $current = null): void
    {
        $existing = $this->repository->findOneBy(['key' => $key]);
        if ($existing instanceof ItemDefinition && $existing !== $current) {
            throw new ConflictHttpException('An item definition with this key already exists.');
        }
    }

    private static function normalizeKey(string $key): string
    {
        $key = mb_strtolower(trim($key));
        if ('' === $key) {
            throw new UnprocessableEntityHttpException('An item definition key cannot be empty.');
        }
        if (1 !== preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $key)) {
            throw new UnprocessableEntityHttpException('The item definition key contains invalid characters.');
        }

        return $key;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new UnprocessableEntityHttpException('An item definition name cannot be empty.');
        }

        return $name;
    }

    private static function normalizeNullable(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private static function normalizePositive(int $value, string $message): int
    {
        if ($value <= 0) {
            throw new UnprocessableEntityHttpException($message);
        }

        return $value;
    }

    private static function normalizeNullablePositive(?int $value, string $message): ?int
    {
        if (null === $value) {
            return null;
        }
        if ($value <= 0) {
            throw new UnprocessableEntityHttpException($message);
        }

        return $value;
    }
}
