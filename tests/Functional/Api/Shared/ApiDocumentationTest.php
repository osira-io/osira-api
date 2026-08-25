<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Shared;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ApiDocumentationTest extends ApiTestCase
{
    public function testHomepageRedirectsToSwaggerUi(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        self::assertResponseRedirects('/api/docs');
    }

    public function testSwaggerUiIsAvailable(): void
    {
        $client = self::createClient();

        $response = $client->request('GET', '/api/docs', [
            'headers' => ['accept' => 'text/html'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
        self::assertStringContainsString('swagger-ui', $response->getContent(false));
        self::assertStringContainsString('Osira API', $response->getContent(false));
    }

    public function testNodeManagementContractContainsOnlyBusinessPatchFields(): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/api/docs.jsonopenapi', [
            'headers' => ['accept' => 'application/vnd.openapi+json'],
        ]);
        $document = self::responseObject($response);
        $paths = self::objectAt($document, 'paths');
        $nodePath = self::objectAt($paths, '/api/nodes/{id}');
        $nodeGroupCollectionPath = self::objectAt($paths, '/api/node-groups');
        $nodeGroupItemPath = self::objectAt($paths, '/api/node-groups/{id}');
        $userCollectionPath = self::objectAt($paths, '/api/users');
        $userItemPath = self::objectAt($paths, '/api/users/{id}');
        $roleCollectionPath = self::objectAt($paths, '/api/roles');
        $roleItemPath = self::objectAt($paths, '/api/roles/{id}');
        $permissionCollectionPath = self::objectAt($paths, '/api/permissions');
        $permissionItemPath = self::objectAt($paths, '/api/permissions/{id}');
        $currentUserPath = self::objectAt($paths, '/api/me');
        $metricInstantPath = self::objectAt($paths, '/api/metrics/query');
        $metricRangePath = self::objectAt($paths, '/api/metrics/query-range');
        $nodeMetricsPath = self::objectAt($paths, '/api/nodes/{id}/metrics');
        $agentConfigPath = self::objectAt($paths, '/api/agent/config');
        $agentCredentialRotatePath = self::objectAt($paths, '/api/agent/credentials/rotate');
        $agentCredentialRevokePath = self::objectAt($paths, '/api/agent-credentials/{id}/revoke');
        $incidentCollectionPath = self::objectAt($paths, '/api/incidents');
        $incidentItemPath = self::objectAt($paths, '/api/incidents/{id}');

        self::assertArrayHasKey('patch', $nodePath);
        self::assertStringContainsString('itemsPerPage', json_encode(self::objectAt($paths, '/api/nodes'), \JSON_THROW_ON_ERROR));
        self::assertArrayHasKey('get', $nodeGroupCollectionPath);
        self::assertArrayHasKey('post', $nodeGroupCollectionPath);
        self::assertArrayHasKey('patch', $nodeGroupItemPath);
        self::assertArrayHasKey('delete', $nodeGroupItemPath);
        self::assertArrayHasKey('get', $userCollectionPath);
        self::assertArrayHasKey('post', $userCollectionPath);
        self::assertArrayHasKey('get', $userItemPath);
        self::assertArrayHasKey('patch', $userItemPath);
        self::assertArrayHasKey('delete', $userItemPath);
        self::assertArrayHasKey('get', $roleCollectionPath);
        self::assertArrayHasKey('post', $roleCollectionPath);
        self::assertArrayHasKey('get', $roleItemPath);
        self::assertArrayHasKey('patch', $roleItemPath);
        self::assertArrayHasKey('delete', $roleItemPath);
        self::assertArrayHasKey('get', $permissionCollectionPath);
        self::assertArrayNotHasKey('post', $permissionCollectionPath);
        self::assertArrayHasKey('get', $permissionItemPath);
        self::assertArrayHasKey('get', $currentUserPath);
        self::assertArrayHasKey('patch', $currentUserPath);
        self::assertArrayHasKey('get', $metricInstantPath);
        self::assertArrayHasKey('get', $metricRangePath);
        self::assertArrayHasKey('get', $nodeMetricsPath);
        self::assertArrayHasKey('get', $agentConfigPath);
        self::assertArrayHasKey('post', $agentCredentialRotatePath);
        self::assertArrayHasKey('post', $agentCredentialRevokePath);
        self::assertArrayHasKey('get', $incidentCollectionPath);
        self::assertArrayHasKey('get', $incidentItemPath);
        self::assertArrayNotHasKey('post', $incidentCollectionPath);
        self::assertStringContainsString('alertRule', json_encode($incidentCollectionPath, \JSON_THROW_ON_ERROR));

        $components = self::objectAt($document, 'components');
        $schemas = self::objectAt($components, 'schemas');
        $encoded = json_encode(self::objectAt($schemas, 'Node.UpdateNodeInput.jsonMergePatch'), \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('displayName', $encoded);
        self::assertStringContainsString('environment', $encoded);
        self::assertStringContainsString('tags', $encoded);
        self::assertStringContainsString('groups', $encoded);
        self::assertArrayHasKey('NodeCollection', $schemas);
        self::assertArrayHasKey('NodeGroupCollection', $schemas);
        self::assertArrayHasKey('CurrentUser', $schemas);
        $currentUserPatch = json_encode(self::objectAt($schemas, 'CurrentUser.UpdateCurrentUserInput.jsonMergePatch'), \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('locale', $currentUserPatch);
        self::assertStringNotContainsString('email', $currentUserPatch);
        self::assertStringNotContainsString('roles', $currentUserPatch);
        self::assertStringNotContainsString('permissions', $currentUserPatch);
        self::assertStringNotContainsString('hostname', $encoded);
        self::assertStringNotContainsString('architecture', $encoded);
        self::assertStringNotContainsString('secretHash', json_encode($document, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('tokenHash', json_encode($document, \JSON_THROW_ON_ERROR));
    }

    public function testTagsAreInDeterministicOrder(): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/api/docs.jsonopenapi', [
            'headers' => ['accept' => 'application/vnd.openapi+json'],
        ]);
        $document = self::responseObject($response);
        $tags = $document['tags'] ?? null;
        self::assertIsArray($tags);

        $names = array_map(static function (mixed $tag): string {
            self::assertIsArray($tag);
            $name = $tag['name'] ?? null;
            self::assertIsString($name);

            return $name;
        }, $tags);

        self::assertSame([
            'CurrentUser',
            'User',
            'Role',
            'Permission',
            'Node',
            'NodeGroup',
            'MonitoringTemplate',
            'ItemDefinition',
            'Metric',
            'Incident',
            'EnrollmentToken',
            'AgentEnrollment',
            'AgentConfig',
            'AgentCredential',
            'Audit',
        ], $names);
    }

    /** @return array<string, mixed> */
    private static function responseObject(ResponseInterface $response): array
    {
        $decoded = json_decode($response->getContent(false), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        $object = [];
        foreach ($decoded as $key => $value) {
            self::assertIsString($key);
            $object[$key] = $value;
        }

        return $object;
    }

    /** @param array<string, mixed> $object
     * @return array<string, mixed>
     */
    private static function objectAt(array $object, string $key): array
    {
        $value = $object[$key] ?? null;
        self::assertIsArray($value);

        $nested = [];
        foreach ($value as $nestedKey => $nestedValue) {
            self::assertIsString($nestedKey);
            $nested[$nestedKey] = $nestedValue;
        }

        return $nested;
    }
}
