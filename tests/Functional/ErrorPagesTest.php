<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Basic error page smoke tests.
 * NOTE: These tests verify that 404 responses are returned for non-existent routes.
 * Testing the visual appearance of custom error pages requires additional configuration
 * in the test environment which is not currently set up.
 */
class ErrorPagesTest extends WebTestCase
{
    public function testNonexistentPageReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/this-page-does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testNonexistentRouteReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/invalid/route/that/does/not/exist');

        $this->assertResponseStatusCodeSame(404);
    }
}
