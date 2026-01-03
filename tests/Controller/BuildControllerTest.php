<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BuildControllerTest extends WebTestCase
{
    public function testIndexPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/build');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Build');
    }

    public function testProjectDetailPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/build/test-project-one');

        // Should load successfully (stub template with fallback content)
        $this->assertResponseIsSuccessful();
    }
}
