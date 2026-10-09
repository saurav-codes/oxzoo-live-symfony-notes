<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Needs a migrated MariaDB in MYSQL_URL; skipped without one. */
final class DbTest extends WebTestCase
{
    protected function setUp(): void
    {
        if ('' === (string) getenv('MYSQL_URL')) {
            self::markTestSkipped('MYSQL_URL is not set');
        }
    }

    public function testProbePasses(): void
    {
        $client = static::createClient();
        $client->request('GET', '/_zoo/probe');
        self::assertResponseIsSuccessful();
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($body['ok'], json_encode($body['checks']));
        self::assertSame(['mysql', 'migrations'], array_column($body['checks'], 'id'));
        self::assertSame(['MYSQL_URL'], $body['checks'][0]['env']);
        self::assertSame(['name' => 'MYSQL_URL', 'fp' => substr(hash('sha256', getenv('MYSQL_URL')), -4), 'role' => 'service'], $body['vars'][0]);
        self::assertStringNotContainsString((string) parse_url(getenv('MYSQL_URL'), \PHP_URL_PASS), $client->getResponse()->getContent());
    }

    public function testNoteCrud(): void
    {
        $client = static::createClient();
        $client->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
        $title = 'zoo note '.bin2hex(random_bytes(4));

        $crawler = $client->request('GET', '/');
        $client->submit($crawler->selectButton('Save')->form(['note[title]' => '', 'note[body]' => 'x']));
        self::assertResponseStatusCodeSame(422);

        $crawler = $client->request('GET', '/');
        $client->submit($crawler->selectButton('Save')->form(['note[title]' => $title, 'note[body]' => 'hello']));
        self::assertResponseStatusCodeSame(303);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.note h3', $title);

        $client->click($crawler->filter('.note')->first()->selectLink('edit')->link());
        $client->submit($client->getCrawler()->selectButton('Save')->form(['note[title]' => $title.' edited']));
        self::assertResponseStatusCodeSame(303);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.note h3', $title.' edited');

        $client->setServerParameter('HTTP_SEC_FETCH_SITE', 'cross-site');
        $client->submit($crawler->filter('.note')->first()->selectButton('delete')->form());
        self::assertResponseStatusCodeSame(403);

        $client->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
        $client->submit($crawler->filter('.note')->first()->selectButton('delete')->form());
        self::assertResponseStatusCodeSame(303);
        $client->followRedirect();
        self::assertStringNotContainsString($title, $client->getResponse()->getContent());
    }
}
