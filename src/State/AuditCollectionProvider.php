<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\AuditCollectionOutput;
use App\ApiResource\PaginationMetadata;
use App\Audit\AuditLogReader;
use App\Audit\AuditSearchCriteria;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** @implements ProviderInterface<AuditCollectionOutput> */
final readonly class AuditCollectionProvider implements ProviderInterface
{
    public function __construct(private AuditLogReader $reader, private AuditOutputFactory $outputFactory)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AuditCollectionOutput
    {
        $pagination = PaginationParameters::fromOperation($operation);
        $dateFrom = self::dateParameter($operation, 'dateFrom');
        $dateTo = self::dateParameter($operation, 'dateTo');
        if ($dateFrom instanceof \DateTimeImmutable && $dateTo instanceof \DateTimeImmutable && $dateFrom > $dateTo) {
            throw new BadRequestHttpException('dateFrom must be earlier than or equal to dateTo.');
        }

        $page = $this->reader->search(new AuditSearchCriteria(
            $pagination->page,
            $pagination->itemsPerPage,
            self::stringParameter($operation, 'entity'),
            self::stringParameter($operation, 'entityId'),
            self::stringParameter($operation, 'action'),
            self::stringParameter($operation, 'actor'),
            $dateFrom,
            $dateTo,
        ));
        $totalPages = 0 === $page->totalItems ? 0 : (int) ceil($page->totalItems / $pagination->itemsPerPage);

        return new AuditCollectionOutput(
            array_map($this->outputFactory->create(...), $page->items),
            new PaginationMetadata(
                $pagination->page,
                $pagination->itemsPerPage,
                $page->totalItems,
                $totalPages,
                $pagination->page > 1,
                $pagination->page < $totalPages,
            ),
        );
    }

    private static function dateParameter(Operation $operation, string $name): ?\DateTimeImmutable
    {
        $value = self::stringParameter($operation, $name);
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $value);
        if (false === $date) {
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
        }
        if (!$date instanceof \DateTimeImmutable) {
            throw new BadRequestHttpException(\sprintf('%s must be a valid ISO 8601 date-time.', $name));
        }

        return $date;
    }

    private static function stringParameter(Operation $operation, string $name): ?string
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
