<?php

namespace App\Tests\Controller;

use App\Repository\GuestbookEntryRepository;
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
