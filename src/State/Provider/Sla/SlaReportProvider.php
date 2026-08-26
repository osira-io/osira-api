<?php

declare(strict_types=1);

namespace App\State\Provider\Sla;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Sla\SlaReportOutput;
use App\Service\Sla\SlaManager;
use App\Service\Sla\SlaPeriodResolver;
use App\Service\Sla\SlaReportService;

/** @implements ProviderInterface<SlaReportOutput> */
final readonly class SlaReportProvider implements ProviderInterface
{
    public function __construct(private SlaManager $manager, private SlaPeriodResolver $periodResolver, private SlaReportService $reportService)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SlaReportOutput
    {
        $id = $uriVariables['id'] ?? '';
        $sla = $this->manager->find(\is_string($id) ? $id : '');
        $from = self::parameter($operation, 'from');
        $to = self::parameter($operation, 'to');

        return $this->reportService->create($sla, $this->periodResolver->resolve($sla->periodType(), $from, $to));
    }

    private static function parameter(Operation $operation, string $name): ?string
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
