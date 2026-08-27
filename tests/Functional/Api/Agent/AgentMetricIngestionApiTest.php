<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Agent;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Agent\Agent;
use App\Entity\Agent\AgentCredential;
use App\Entity\Alert\AlertOperator;
use App\Entity\Alert\AlertRule;
use App\Entity\Alert\AlertRuleImpactType;
use App\Entity\Alert\AlertSeverity;
use App\Entity\Incident\Incident;
use App\Entity\Incident\IncidentStatus;
use App\Entity\Monitoring\ItemDefinition;
use App\Entity\Monitoring\ItemValueType;
use App\Entity\Monitoring\MonitoringTemplate;
use App\Entity\Node\Node;
use App\Entity\NodeGroup\NodeGroup;
use App\Repository\Incident\IncidentRepository;
use App\Security\Auth\TokenGenerator;
use App\Security\Auth\TokenHasher;
use App\Service\Metrics\VictoriaMetricsClientProxy;
use App\Service\Metrics\VictoriaMetricsUnavailableException;
use App\Tests\Functional\Support\FakeVictoriaMetricsClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class AgentMetricIngestionApiTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private FakeVictoriaMetricsClient $victoriaMetrics;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $entityManager = $this->entityManager();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->victoriaMetrics = new FakeVictoriaMetricsClient();
        self::getContainer()->get(VictoriaMetricsClientProxy::class)->useClient($this->victoriaMetrics);
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        if (self::$kernel instanceof \Symfony\Component\HttpKernel\KernelInterface) {
            self::getContainer()->get(VictoriaMetricsClientProxy::class)->reset();
        }
        parent::tearDown();
    }

    public function testRequiresAValidAgentCredential(): void
    {
        $client = self::createJsonClient();
        $payload = ['samples' => [['itemKey' => 'custom.cpu', 'value' => 42.5]]];

        $client->request('POST', '/api/agent/metrics', ['json' => $payload]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('POST', '/api/agent/metrics', [
            'auth_bearer' => TokenGenerator::AGENT_PREFIX.'invalid',
            'json' => $payload,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame([], $this->victoriaMetrics->importedBatches);
    }

    public function testAcceptsABatchAndDerivesTheNodeFromTheAgent(): void
    {
        [$token, $node] = $this->seedAgentWithMonitoring();
        $response = self::createJsonClient()->request('POST', '/api/agent/metrics', [
            'auth_bearer' => $token,
            'json' => ['samples' => [
                ['itemKey' => 'custom.cpu', 'value' => 82.5, 'collectedAt' => '2026-08-27T10:00:00+00:00'],
                ['itemKey' => 'custom.load', 'value' => 4],
                ['itemKey' => 'custom.ready', 'value' => true],
            ]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['accepted' => 3], $response->toArray());
        self::assertCount(1, $this->victoriaMetrics->importedBatches);
        $samples = $this->victoriaMetrics->importedBatches[0];
        self::assertSame(['82.5', '4', '1'], array_map(static fn ($sample): string => $sample->value, $samples));
        self::assertSame([(string) $node->id()], array_values(array_unique(array_map(static fn ($sample): string => $sample->nodeId, $samples))));
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsTheWholeInvalidBatch(array $payload): void
    {
        [$token] = $this->seedAgentWithMonitoring();
        self::createJsonClient()->request('POST', '/api/agent/metrics', [
            'auth_bearer' => $token,
            'json' => $payload,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([], $this->victoriaMetrics->importedBatches);
    }

    /** @return \Generator<string, array{0: array<string, mixed>}> */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'empty batch' => [['samples' => []]];
        yield 'unknown item' => [['samples' => [['itemKey' => 'unknown', 'value' => 1]]]];
        yield 'wrong numeric type' => [['samples' => [['itemKey' => 'custom.load', 'value' => '4']]]];
        yield 'invalid timestamp' => [['samples' => [['itemKey' => 'custom.cpu', 'value' => 1.0, 'collectedAt' => 'tomorrow']]]];
        yield 'timestamp without timezone' => [['samples' => [['itemKey' => 'custom.cpu', 'value' => 1.0, 'collectedAt' => '2026-08-27T10:00:00']]]];
        yield 'timestamp too far in future' => [['samples' => [['itemKey' => 'custom.cpu', 'value' => 1.0, 'collectedAt' => '2099-08-27T10:00:00+00:00']]]];
        yield 'oversized batch' => [['samples' => array_fill(0, 501, ['itemKey' => 'custom.cpu', 'value' => 1.0])]];
    }

    public function testReturnsACleanErrorWhenVictoriaMetricsIsUnavailable(): void
    {
        [$token] = $this->seedAgentWithMonitoring();
        $this->victoriaMetrics->importException = new VictoriaMetricsUnavailableException('VictoriaMetrics is unavailable.');

        $response = self::createJsonClient()->request('POST', '/api/agent/metrics', [
            'auth_bearer' => $token,
            'json' => ['samples' => [['itemKey' => 'custom.cpu', 'value' => 1.0]]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        self::assertStringNotContainsString($token, $response->getContent(false));
    }

    public function testRejectsAnItemThatIsNotEffectiveForTheAuthenticatedNode(): void
    {
        [$firstToken] = $this->seedAgentWithMonitoring('node-a');
        [, $secondNode] = $this->seedAgentWithMonitoring('node-b', 'other.metric');

        self::createJsonClient()->request('POST', '/api/agent/metrics', [
            'auth_bearer' => $firstToken,
            'json' => [
                'nodeId' => (string) $secondNode->id(),
                'samples' => [['itemKey' => 'other.metric', 'value' => 1.0]],
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame([], $this->victoriaMetrics->importedBatches);
    }

    public function testIngestionDrivesRequiredOccurrencesAndIncidentRecoveryEndToEnd(): void
    {
        [$token, $node, $item, $template] = $this->seedAgentWithMonitoring();
        $now = new \DateTimeImmutable('2026-08-27T10:00:00+00:00');
        $rule = new AlertRule('CPU high', 'CPU usage is high', $item, AlertOperator::GT, '90', '80', 300, 3, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, $now);
        $rule->assignToTemplate($template);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        $this->victoriaMetrics->rangeResult = [FakeVictoriaMetricsClient::series(
            ['node_id' => (string) $node->id(), 'item_key' => $item->key()],
            [
                ['2026-08-27T09:59:58+00:00', '91'],
                ['2026-08-27T09:59:59+00:00', '92'],
                ['2026-08-27T10:00:00+00:00', '93'],
            ],
        )];
        $client = self::createJsonClient();
        $client->request('POST', '/api/agent/metrics', [
            'auth_bearer' => $token,
            'json' => ['samples' => [
                ['itemKey' => $item->key(), 'value' => 91.0, 'collectedAt' => '2026-08-27T09:59:58+00:00'],
                ['itemKey' => $item->key(), 'value' => 92.0, 'collectedAt' => '2026-08-27T09:59:59+00:00'],
                ['itemKey' => $item->key(), 'value' => 93.0, 'collectedAt' => '2026-08-27T10:00:00+00:00'],
            ]],
        ]);
        self::assertResponseIsSuccessful();
        $incident = self::getContainer()->get(IncidentRepository::class)->findOneBy([]);
        self::assertInstanceOf(Incident::class, $incident);
        self::assertSame(IncidentStatus::FIRING, $incident->status());
        self::assertSame(1, $incident->occurrences());

        // A network retry re-imports the same natural identity (Node + item key + timestamp)
        // and must not count the same evaluation twice.
        $client->request('POST', '/api/agent/metrics', [
            'auth_bearer' => $token,
            'json' => ['samples' => [
                ['itemKey' => $item->key(), 'value' => 91.0, 'collectedAt' => '2026-08-27T09:59:58+00:00'],
                ['itemKey' => $item->key(), 'value' => 92.0, 'collectedAt' => '2026-08-27T09:59:59+00:00'],
                ['itemKey' => $item->key(), 'value' => 93.0, 'collectedAt' => '2026-08-27T10:00:00+00:00'],
            ]],
        ]);
        self::assertResponseIsSuccessful();
        $incident = self::getContainer()->get(IncidentRepository::class)->find($incident->id());
        self::assertInstanceOf(Incident::class, $incident);
        self::assertSame(1, $incident->occurrences());

        $this->victoriaMetrics->rangeResult = [FakeVictoriaMetricsClient::series(
            ['node_id' => (string) $node->id(), 'item_key' => $item->key()],
            [['2026-08-27T10:01:00+00:00', '70']],
        )];
        $client->request('POST', '/api/agent/metrics', [
            'auth_bearer' => $token,
            'json' => ['samples' => [['itemKey' => $item->key(), 'value' => 70.0, 'collectedAt' => '2026-08-27T10:01:00+00:00']]],
        ]);
        self::assertResponseIsSuccessful();
        $incident = self::getContainer()->get(IncidentRepository::class)->find($incident->id());
        self::assertInstanceOf(Incident::class, $incident);
        self::assertSame(IncidentStatus::RESOLVED, $incident->status());
    }

    public function testRetryOfTheSameSampleBeforeFiringDoesNotCountAsAnExtraOccurrence(): void
    {
        // lastTriggeredAt only guards an Incident that already exists. Before the first
        // FIRING transition there is no Incident row at all, so the only thing that can
        // prevent a retransmitted sample from being double-counted is that requiredOccurrences
        // is computed from distinct collection instants, not raw VictoriaMetrics points.
        [$token, $node, $item, $template] = $this->seedAgentWithMonitoring();
        $this->victoriaMetrics->deriveRangeFromImports = true;
        $rule = $this->createHighCpuRule($item, $template, requiredOccurrences: 3);
        $client = self::createJsonClient();

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:10+00:00');
        self::assertSame([], $this->activeIncidents($rule, $node));

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:10+00:00');
        self::assertSame([], $this->activeIncidents($rule, $node), 'the exact retransmission at t=10 must not count as a second occurrence');

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:20+00:00');
        self::assertSame([], $this->activeIncidents($rule, $node), 'only two distinct instants (t=10, t=20) have been observed');

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:30+00:00');
        $incidents = $this->activeIncidents($rule, $node);
        self::assertCount(1, $incidents);
        self::assertSame(IncidentStatus::FIRING, $incidents[0]->status());
        self::assertSame(1, $incidents[0]->occurrences());
    }

    public function testADuplicateSampleWithinTheSameBatchCountsOnce(): void
    {
        [$token, $node, $item, $template] = $this->seedAgentWithMonitoring();
        $this->victoriaMetrics->deriveRangeFromImports = true;
        $rule = $this->createHighCpuRule($item, $template, requiredOccurrences: 2);
        $client = self::createJsonClient();

        $this->sendSamples($client, $token, [
            ['itemKey' => $item->key(), 'value' => 95.0, 'collectedAt' => '2026-08-27T10:00:10+00:00'],
            ['itemKey' => $item->key(), 'value' => 95.0, 'collectedAt' => '2026-08-27T10:00:10+00:00'],
        ]);
        self::assertSame([], $this->activeIncidents($rule, $node), 'a duplicated sample within the same batch is a single distinct instant');

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:20+00:00');
        $incidents = $this->activeIncidents($rule, $node);
        self::assertCount(1, $incidents);
        self::assertSame(IncidentStatus::FIRING, $incidents[0]->status());
    }

    public function testARetransmittedFullBatchDoesNotChangeTheBusinessOutcome(): void
    {
        [$token, $node, $item, $template] = $this->seedAgentWithMonitoring();
        $this->victoriaMetrics->deriveRangeFromImports = true;
        $rule = $this->createHighCpuRule($item, $template, requiredOccurrences: 3);
        $client = self::createJsonClient();

        $batch = [
            ['itemKey' => $item->key(), 'value' => 95.0, 'collectedAt' => '2026-08-27T10:00:10+00:00'],
            ['itemKey' => $item->key(), 'value' => 95.0, 'collectedAt' => '2026-08-27T10:00:20+00:00'],
        ];
        $this->sendSamples($client, $token, $batch);
        self::assertSame([], $this->activeIncidents($rule, $node));

        // Simulates the agent retrying the same HTTP batch after a network timeout.
        $this->sendSamples($client, $token, $batch);
        self::assertSame([], $this->activeIncidents($rule, $node), 'retransmitting the same batch must not advance the occurrence count');

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:30+00:00');
        $incidents = $this->activeIncidents($rule, $node);
        self::assertCount(1, $incidents);
        self::assertSame(IncidentStatus::FIRING, $incidents[0]->status());
    }

    public function testOutOfOrderSamplesNeitherDoubleCountNorCorruptState(): void
    {
        // Each request evaluates at the latest collectedAt among ITS OWN samples for a
        // given item, looking backward across the whole evaluation window. An out-of-order
        // (earlier) sample arriving after a later one does not retroactively re-run the
        // evaluation that already happened for that later instant: firing is only detected
        // once a subsequent request's own evaluatedAt reaches far enough forward again.
        // Nothing is double-counted and no sample is lost — this is the intended, documented
        // behavior, not a defect.
        [$token, $node, $item, $template] = $this->seedAgentWithMonitoring();
        $this->victoriaMetrics->deriveRangeFromImports = true;
        $rule = $this->createHighCpuRule($item, $template, requiredOccurrences: 3);
        $client = self::createJsonClient();

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:30+00:00');
        self::assertSame([], $this->activeIncidents($rule, $node));

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:10+00:00');
        self::assertSame([], $this->activeIncidents($rule, $node), 'evaluating at t=10 must not see the future t=30 point');

        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:20+00:00');
        self::assertSame([], $this->activeIncidents($rule, $node), 'evaluating at t=20 sees t=10 and t=20 only: still 2 occurrences');

        // A later sample whose own evaluatedAt covers the whole window now sees every
        // previously stored instant (10, 20, 30, 40), regardless of the order they arrived in.
        $this->sendSample($client, $token, $item->key(), 95.0, '2026-08-27T10:00:40+00:00');
        $incidents = $this->activeIncidents($rule, $node);
        self::assertCount(1, $incidents);
        self::assertSame(IncidentStatus::FIRING, $incidents[0]->status());
        self::assertSame(1, $incidents[0]->occurrences());
    }

    public function testDeduplicationIsScopedPerItemDefinition(): void
    {
        [$token, $node, $cpu, $template] = $this->seedAgentWithMonitoring();
        $this->victoriaMetrics->deriveRangeFromImports = true;
        $memoryKey = 'custom.load';
        $memory = $this->itemDefinitionByKey($memoryKey);
        $cpuRule = $this->createHighCpuRule($cpu, $template, requiredOccurrences: 1);
        $memoryRule = new AlertRule('Load high', 'Load usage is high', $memory, AlertOperator::GT, '3', null, 300, 1, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, new \DateTimeImmutable('2026-08-27T09:00:00+00:00'));
        $memoryRule->assignToTemplate($template);
        $this->entityManager()->persist($memoryRule);
        $this->entityManager()->flush();
        $client = self::createJsonClient();

        $this->sendSamples($client, $token, [
            ['itemKey' => $cpu->key(), 'value' => 95.0, 'collectedAt' => '2026-08-27T10:00:10+00:00'],
            ['itemKey' => $memoryKey, 'value' => 4, 'collectedAt' => '2026-08-27T10:00:10+00:00'],
        ]);

        $cpuIncidents = $this->activeIncidents($cpuRule, $node);
        $memoryIncidents = $this->activeIncidents($memoryRule, $node);
        self::assertCount(1, $cpuIncidents);
        self::assertCount(1, $memoryIncidents);
        self::assertNotSame($cpuIncidents[0]->id(), $memoryIncidents[0]->id());
    }

    public function testDeduplicationIsScopedPerNode(): void
    {
        [$firstToken, $firstNode, $firstItem, $firstTemplate] = $this->seedAgentWithMonitoring('node-a');
        [$secondToken, $secondNode, $secondItem, $secondTemplate] = $this->seedAgentWithMonitoring('node-b', 'other.cpu');
        $this->victoriaMetrics->deriveRangeFromImports = true;
        $firstRule = $this->createHighCpuRule($firstItem, $firstTemplate, requiredOccurrences: 1);
        $secondRule = $this->createHighCpuRule($secondItem, $secondTemplate, requiredOccurrences: 1);
        $client = self::createJsonClient();

        $this->sendSample($client, $firstToken, $firstItem->key(), 95.0, '2026-08-27T10:00:10+00:00');
        $this->sendSample($client, $secondToken, $secondItem->key(), 95.0, '2026-08-27T10:00:10+00:00');

        $firstIncidents = $this->activeIncidents($firstRule, $firstNode);
        $secondIncidents = $this->activeIncidents($secondRule, $secondNode);
        self::assertCount(1, $firstIncidents);
        self::assertCount(1, $secondIncidents);
        self::assertNotSame($firstIncidents[0]->id(), $secondIncidents[0]->id());
    }

    private function createHighCpuRule(ItemDefinition $item, MonitoringTemplate $template, int $requiredOccurrences): AlertRule
    {
        $rule = new AlertRule('CPU high', 'CPU usage is high', $item, AlertOperator::GT, '90', null, 300, $requiredOccurrences, AlertSeverity::CRITICAL, AlertRuleImpactType::AVAILABILITY, true, new \DateTimeImmutable('2026-08-27T09:00:00+00:00'));
        $rule->assignToTemplate($template);
        $this->entityManager()->persist($rule);
        $this->entityManager()->flush();

        return $rule;
    }

    /** @param list<array{itemKey: string, value: mixed, collectedAt?: string}> $samples */
    private function sendSamples(Client $client, string $token, array $samples): void
    {
        $client->request('POST', '/api/agent/metrics', ['auth_bearer' => $token, 'json' => ['samples' => $samples]]);
        self::assertResponseIsSuccessful();
    }

    private function sendSample(Client $client, string $token, string $itemKey, int|float|bool $value, string $collectedAt): void
    {
        $this->sendSamples($client, $token, [['itemKey' => $itemKey, 'value' => $value, 'collectedAt' => $collectedAt]]);
    }

    /** @return list<Incident> */
    private function activeIncidents(AlertRule $rule, Node $node): array
    {
        return self::getContainer()->get(IncidentRepository::class)->findActiveForRuleAndNode((string) $rule->id(), (string) $node->id());
    }

    private function itemDefinitionByKey(string $key): ItemDefinition
    {
        $item = $this->entityManager()->getRepository(ItemDefinition::class)->findOneBy(['key' => $key]);
        self::assertInstanceOf(ItemDefinition::class, $item);

        return $item;
    }

    /** @return array{string, Node, ItemDefinition, MonitoringTemplate} */
    private function seedAgentWithMonitoring(string $hostname = 'metric-node', string $floatKey = 'custom.cpu'): array
    {
        $now = new \DateTimeImmutable('2026-08-27T09:00:00+00:00');
        $node = new Node($hostname, null, 'linux', 'x86_64', $now, $now);
        $agent = new Agent($node, '0.1.0', $now, $now);
        $rawToken = self::getContainer()->get(TokenGenerator::class)->generateAgentToken();
        $credential = new AgentCredential($agent, self::getContainer()->get(TokenHasher::class)->hash($rawToken), $now, null);
        $float = new ItemDefinition($floatKey, 'CPU', null, '%', ItemValueType::FLOAT, 60, 5, 'printf 1', null, true, $now);
        $keySuffix = 'metric-node' === $hostname ? '' : '.'.$hostname;
        $integer = new ItemDefinition('custom.load'.$keySuffix, 'Load', null, null, ItemValueType::INTEGER, 60, 5, 'printf 1', null, true, $now);
        $boolean = new ItemDefinition('custom.ready'.$keySuffix, 'Ready', null, null, ItemValueType::BOOLEAN, 60, 5, 'printf 1', null, true, $now);
        $template = new MonitoringTemplate('Metrics '.$hostname, 'metrics-'.$hostname, null, true, $now);
        $template->replaceItemDefinitions([$float, $integer, $boolean], $now);
        $group = new NodeGroup('Metrics '.$hostname, null, $now);
        $group->replaceMonitoringTemplates([$template], $now);
        $node->replaceGroups([$group]);

        foreach ([$node, $agent, $credential, $float, $integer, $boolean, $template, $group] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();

        return [$rawToken, $node, $float, $template];
    }

    private static function createJsonClient(): Client
    {
        return self::createClient(defaultOptions: ['headers' => ['accept' => 'application/json', 'content-type' => 'application/json']]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
