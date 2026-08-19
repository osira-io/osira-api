<?php

declare(strict_types=1);

namespace App\State\Provider\Metrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Metrics\MetricInstantQueryOutput;
use App\Service\Metrics\MetricLabelFilters;
use App\Service\Metrics\NodeMetricsReader;
use App\Service\Metrics\VictoriaMetricsInvalidResponseException;
use App\Service\Metrics\VictoriaMetricsUnavailableException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** @implements ProviderInterface<MetricInstantQueryOutput> */
final readonly class MetricInstantQueryProvider implements ProviderInterface
{
    public function __construct(
        private NodeMetricsReader $reader,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MetricInstantQueryOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        \assert(null !== $request);

        try {
            return $this->reader->queryInstant(
                $request->query->getString('nodeId'),
                $request->query->getString('itemKey'),
                new MetricLabelFilters(
                    self::nullableString($request->query->get('device')),
                    self::nullableString($request->query->get('interface')),
                    self::nullableString($request->query->get('container')),
                ),
            );
        } catch (VictoriaMetricsInvalidResponseException $exception) {
            throw new HttpException(502, 'VictoriaMetrics returned an invalid response.', $exception);
        } catch (VictoriaMetricsUnavailableException $exception) {
            throw new ServiceUnavailableHttpException(null, 'VictoriaMetrics is unavailable.', $exception);
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
