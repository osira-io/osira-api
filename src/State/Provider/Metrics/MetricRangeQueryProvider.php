<?php

declare(strict_types=1);

namespace App\State\Provider\Metrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Metrics\MetricRangeQueryOutput;
use App\Service\Metrics\MetricLabelFilters;
use App\Service\Metrics\NodeMetricsReader;
use App\Service\Metrics\VictoriaMetricsInvalidResponseException;
use App\Service\Metrics\VictoriaMetricsUnavailableException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** @implements ProviderInterface<MetricRangeQueryOutput> */
final readonly class MetricRangeQueryProvider implements ProviderInterface
{
    public function __construct(
        private NodeMetricsReader $reader,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MetricRangeQueryOutput
    {
        $request = $this->requestStack->getCurrentRequest();
        \assert(null !== $request);

        try {
            return $this->reader->queryRange(
                $request->query->getString('nodeId'),
                $request->query->getString('itemKey'),
                $this->parseDate($request->query->getString('from')),
                $this->parseDate($request->query->getString('to')),
                $request->query->getInt('stepSeconds'),
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

    private function parseDate(string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $exception) {
            throw new UnprocessableEntityHttpException(\sprintf('Invalid datetime "%s".', $value), $exception);
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
