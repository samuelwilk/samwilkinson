<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StudioControllerTest extends WebTestCase
{
    public function testIndexPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/studio');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Studio');
    }

    public function testDesktopShowsThreeJSScene(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/studio');

        $this->assertResponseIsSuccessful();

        // Check for Three.js canvas element (desktop)
        $this->assertSelectorExists('[data-controller="studio-papers"]');
        $this->assertSelectorExists('canvas[data-studio-papers-target="canvas"]');
    }

    public function testMobileShowsCardGrid(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/studio');

        $this->assertResponseIsSuccessful();

        // Check for mobile card grid
        $this->assertSelectorExists('.block.md\\:hidden');
        $this->assertSelectorExists('.post-card');
    }
}
