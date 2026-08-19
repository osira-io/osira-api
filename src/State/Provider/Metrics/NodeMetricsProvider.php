<?php

declare(strict_types=1);

namespace App\State\Provider\Metrics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Metrics\NodeMetricsOutput;
use App\Service\Metrics\NodeMetricsReader;
use App\Service\Metrics\VictoriaMetricsInvalidResponseException;
use App\Service\Metrics\VictoriaMetricsUnavailableException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** @implements ProviderInterface<NodeMetricsOutput> */
final readonly class NodeMetricsProvider implements ProviderInterface
{
    public function __construct(private NodeMetricsReader $reader)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NodeMetricsOutput
    {
        $id = $uriVariables['id'] ?? '';
        \assert(\is_string($id));

        try {
            return $this->reader->queryNodeSnapshot($id);
        } catch (VictoriaMetricsInvalidResponseException $exception) {
            throw new HttpException(502, 'VictoriaMetrics returned an invalid response.', $exception);
        } catch (VictoriaMetricsUnavailableException $exception) {
            throw new ServiceUnavailableHttpException(null, 'VictoriaMetrics is unavailable.', $exception);
        }
    }
}
