<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** HTTP behavior that needs no database: health, CORS, CSRF, routing. */
final class HttpTest extends WebTestCase
{
    private const PANEL = 'https://zoo-control.s1.zoo.sorv.dev';

    public function testHealthShape(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_zoo/health', server: ['HTTP_ORIGIN' => self::PANEL]);
        self::assertResponseIsSuccessful();
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('symfony-notes', $body['name']);
        self::assertSame('local', $body['server']);
        self::assertSame('unknown', $body['release']);
        self::assertStringStartsWith('php ', $body['build']['runtime']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $body['started_at']);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', self::PANEL);
        self::assertStringContainsString('Origin', $client->getResponse()->headers->get('Vary'));
    }

    public function testCorsUnlistedOriginGetsNoHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_zoo/health', server: ['HTTP_ORIGIN' => 'https://evil.example']);
        self::assertResponseIsSuccessful();
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testCorsPreflight(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/_zoo/probe', server: ['HTTP_ORIGIN' => 'http://localhost:5173']);
        self::assertResponseStatusCodeSame(204);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertResponseHeaderSame('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        self::assertResponseHeaderSame('Access-Control-Allow-Headers', 'Content-Type');
        self::assertResponseHeaderSame('Access-Control-Max-Age', '600');
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Credentials'));

        $client->request('OPTIONS', '/_zoo/probe', server: ['HTTP_ORIGIN' => 'https://evil.example']);
        self::assertResponseStatusCodeSame(204);
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testCorsNotOnAppPages(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/notes/1/delete', server: ['HTTP_ORIGIN' => self::PANEL]);
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testCrossSiteDeleteIsRefused(): void
    {
        $client = static::createClient();
        $client->request('POST', '/notes/1/delete', ['_token' => 'csrf-token'], server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testIdMustBeNumeric(): void
    {
        $client = static::createClient();
        $client->request('GET', '/notes/abc/edit');
        self::assertResponseStatusCodeSame(404);
    }
}
