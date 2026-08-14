<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
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
}
