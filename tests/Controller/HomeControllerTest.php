<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testIndexPageLoads(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sam Wilkinson');
        $this->assertSelectorExists('.object-card');
    }

    public function testIndexShowsThreeCards(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertCount(3, $crawler->filter('.object-card'));
    }

    public function testBuildCardLinksCorrectly(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertSelectorExists('a[href="/build"]');
        $this->assertSelectorTextContains('a[href="/build"]', 'Build');
    }

    public function testStillsCardLinksCorrectly(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertSelectorExists('a[href="/stills"]');
        $this->assertSelectorTextContains('a[href="/stills"]', 'Stills');
    }

    public function testStudioCardLinksCorrectly(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertSelectorExists('a[href="/studio"]');
        $this->assertSelectorTextContains('a[href="/studio"]', 'Studio');
    }

    public function testDynamicStatusDisplaysCorrectly(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // Each card should have a status element
        $statusElements = $crawler->filter('.museum-value');
        $this->assertGreaterThanOrEqual(3, count($statusElements));

        // Status should be either "Q[N] YYYY" format or "Coming soon"
        foreach ($statusElements as $element) {
            $text = trim($element->textContent);
            $this->assertMatchesRegularExpression('/^(Q[1-4] \\d{4}|Coming soon|\\d{4})$/', $text);
        }
    }

    public function testFormatQuarterMethod(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $controller = $container->get('App\Controller\HomeController');

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('formatQuarter');
        $method->setAccessible(true);

        // Test Q1 (January)
        $date = new \DateTime('2025-01-15');
        $this->assertEquals('Q1 2025', $method->invoke($controller, $date));

        // Test Q2 (April)
        $date = new \DateTime('2025-04-15');
        $this->assertEquals('Q2 2025', $method->invoke($controller, $date));

        // Test Q3 (July)
        $date = new \DateTime('2025-07-15');
        $this->assertEquals('Q3 2025', $method->invoke($controller, $date));

        // Test Q4 (October)
        $date = new \DateTime('2025-10-15');
        $this->assertEquals('Q4 2025', $method->invoke($controller, $date));

        // Test null date
        $this->assertEquals('Coming soon', $method->invoke($controller, null));
    }

    public function testPageHasProperMetaTags(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertSelectorExists('meta[name="description"]');
        $this->assertSelectorTextContains('title', 'Sam Wilkinson');
    }
}
