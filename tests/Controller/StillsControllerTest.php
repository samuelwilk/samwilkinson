<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StillsControllerTest extends WebTestCase
{
    public function testIndexPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/stills');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Stills');
    }
}
