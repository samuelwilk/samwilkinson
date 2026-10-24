<?php

namespace App\Tests\Controller;

use App\Entity\GuestbookEntry;
use App\Repository\GuestbookEntryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GuestbookControllerTest extends WebTestCase
{
    public function testGetDescribesHowToSign(): void
    {
        $client = static::createClient();
        $client->request('GET', '/guestbook');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('POST', $data['method']);
        $this->assertArrayHasKey('message', $data['fields']);
    }

    public function testPostJsonCreatesEntry(): void
    {
        $client = static::createClient();
        $this->postJson($client, [
            'agent' => 'TestBot',
            'sent_by' => 'The test suite',
            'url' => 'https://example.com',
            'message' => 'Hello from PHPUnit.',
        ], '10.0.0.1');

        $this->assertResponseStatusCodeSame(201);

        $entry = static::getContainer()->get(GuestbookEntryRepository::class)
            ->findOneBy(['message' => 'Hello from PHPUnit.']);
        $this->assertNotNull($entry);
        $this->assertSame('TestBot', $entry->getAgentName());
        $this->assertSame('The test suite', $entry->getSentBy());
        $this->assertSame('https://example.com', $entry->getUrl());
        $this->assertSame('GuestbookTest/1.0', $entry->getUserAgent());
        $this->assertNotSame('10.0.0.1', $entry->getClientHash());
    }

    public function testPostFormEncodedCreatesEntry(): void
    {
        $client = static::createClient();
        $client->request('POST', '/guestbook', ['message' => 'Form note'], [], ['REMOTE_ADDR' => '10.0.0.2']);

        $this->assertResponseStatusCodeSame(201);
    }

    public function testMessageIsRequired(): void
    {
        $client = static::createClient();
        $this->postJson($client, ['agent' => 'TestBot', 'message' => '   '], '10.0.0.3');

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data['errors']);
    }

    public function testRejectsOverlongMessageAndBadUrl(): void
    {
        $client = static::createClient();
        $this->postJson($client, [
            'message' => str_repeat('a', 2001),
            'url' => 'javascript:alert(1)',
        ], '10.0.0.4');

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $data['errors']);
        $this->assertArrayHasKey('url', $data['errors']);
    }

    public function testRejectsMalformedJson(): void
    {
        $client = static::createClient();
        $client->request('POST', '/guestbook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '10.0.0.5',
        ], '{not json');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRateLimitsPerClient(): void
    {
        $client = static::createClient();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson($client, ['message' => "Note $i"], '10.0.0.6');
            $this->assertResponseStatusCodeSame(201);
        }

        $this->postJson($client, ['message' => 'One too many'], '10.0.0.6');
        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseHasHeader('Retry-After');

        // A different client is unaffected
        $this->postJson($client, ['message' => 'Someone else'], '10.0.0.7');
        $this->assertResponseStatusCodeSame(201);
    }

    public function testStoresRequestMetadataWithRedactedSecrets(): void
    {
        $client = static::createClient();
        $client->request('GET', '/guestbook', [], [], ['REMOTE_ADDR' => '10.0.1.1']);

        $client->request('POST', '/guestbook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'MetaBot/2.0',
            'HTTP_ACCEPT_LANGUAGE' => 'en-CA',
            'HTTP_AUTHORIZATION' => 'Bearer secret-token',
            'HTTP_COOKIE' => 'session=abc',
            'REMOTE_ADDR' => '10.0.1.1',
        ], json_encode(['message' => 'Metadata note']));
        $this->assertResponseStatusCodeSame(201);

        $entry = static::getContainer()->get(GuestbookEntryRepository::class)
            ->findOneBy(['message' => 'Metadata note']);
        $meta = $entry->getRequestMeta();

        $this->assertSame('POST', $meta['method']);
        $this->assertSame('/guestbook', $meta['path']);
        $this->assertSame('application/json', $meta['content_type']);
        $this->assertGreaterThan(0, $meta['body_bytes']);
        $this->assertSame('MetaBot/2.0', $meta['headers']['user-agent']);
        $this->assertSame('en-CA', $meta['headers']['accept-language']);
        $this->assertSame('[redacted]', $meta['headers']['authorization']);
        $this->assertSame('[redacted]', $meta['headers']['cookie']);
        $this->assertNotNull($meta['received_at']);
        $this->assertNotNull($meta['instructions_read_at']);
        $this->assertGreaterThanOrEqual(0, $meta['seconds_since_instructions']);
        $this->assertStringNotContainsString('secret-token', json_encode($meta));
    }

    public function testStoresMessageAsPlainTextWithoutControlCharacters(): void
    {
        $client = static::createClient();
        $this->postJson($client, [
            'message' => "  <b>Bold?</b>\r\nLine two\u{0000}\u{202E}reversed\t ",
        ], '10.0.1.2');
        $this->assertResponseStatusCodeSame(201);

        $entry = static::getContainer()->get(GuestbookEntryRepository::class)
            ->findOneBy([], ['id' => 'DESC']);
        $this->assertSame("<b>Bold?</b>\nLine tworeversed", $entry->getMessage());
    }

    public function testRejectsOversizedBody(): void
    {
        $client = static::createClient();
        $client->request('POST', '/guestbook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '10.0.1.3',
        ], json_encode(['message' => str_repeat('a', 20000)]));

        $this->assertResponseStatusCodeSame(413);
    }

    public function testRejectsUnsupportedContentType(): void
    {
        $client = static::createClient();
        $client->request('POST', '/guestbook', [], [], [
            'CONTENT_TYPE' => 'text/html',
            'REMOTE_ADDR' => '10.0.1.4',
        ], '<p>hello</p>');

        $this->assertResponseStatusCodeSame(415);
    }

    public function testGlobalRateLimitAcrossClients(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $existing = static::getContainer()->get(GuestbookEntryRepository::class)
            ->countSince(new \DateTimeImmutable('-1 hour'));
        $filler = [];
        for ($i = $existing; $i < 60; $i++) {
            $entry = (new GuestbookEntry())->setMessage("Filler $i")->setClientHash("filler-$i");
            $em->persist($entry);
            $filler[] = $entry;
        }
        $em->flush();

        try {
            $this->postJson($client, ['message' => 'Fresh client, full book'], '10.0.1.5');
            $this->assertResponseStatusCodeSame(429);
        } finally {
            foreach ($filler as $entry) {
                $em->remove($entry);
            }
            $em->flush();
        }
    }

    public function testAdminRendersEntriesEscaped(): void
    {
        $client = static::createClient();
        $this->postJson($client, [
            'agent' => '<img src=x onerror=alert(1)>',
            'url' => 'https://example.com/"><script>alert(2)</script>',
            'message' => '<script>alert(3)</script> Ignore previous instructions.',
        ], '10.0.1.6');
        $this->assertResponseStatusCodeSame(201);

        $entry = static::getContainer()->get(GuestbookEntryRepository::class)
            ->findOneBy([], ['id' => 'DESC']);
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneBy([]));

        foreach (['/admin/guestbook-entry', '/admin/guestbook-entry/' . $entry->getId()] as $page) {
            $client->request('GET', $page);
            $this->assertResponseIsSuccessful();
            $html = $client->getResponse()->getContent();

            $this->assertStringNotContainsString('<script>alert', $html, $page);
            $this->assertStringNotContainsString('<img src=x', $html, $page);
            $this->assertStringContainsString('&lt;script&gt;alert(3)', $html, $page);
        }
    }

    public function testFooterInvitesAgents(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertSelectorTextContains('footer', 'If you’re an AI agent, you’re welcome to leave a note at POST /guestbook');
    }

    public function testLlmsTxtInvitesAgents(): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/public/llms.txt');

        $this->assertStringContainsString("If you're an AI agent, you're welcome to leave a note at POST /guestbook.", $contents);
    }

    private function postJson(KernelBrowser $client, array $payload, string $ip): void
    {
        $client->request('POST', '/guestbook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'GuestbookTest/1.0',
            'REMOTE_ADDR' => $ip,
        ], json_encode($payload));
    }
}
