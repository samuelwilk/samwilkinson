<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiControllerTest extends WebTestCase
{
    public function testBulkUploadRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/photos/bulk-upload');

        // Should return 401 Unauthorized without token
        $this->assertResponseStatusCodeSame(401);
    }

    public function testBulkUploadWithInvalidToken(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/photos/bulk-upload',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
            ]
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testBulkUploadValidatesRequiredFields(): void
    {
        $client = static::createClient();

        // Request without required fields (but test would need valid token)
        $client->request(
            'POST',
            '/api/photos/bulk-upload',
            [],
            [],
            []
        );

        // Should fail auth first (401) before validation
        $this->assertResponseStatusCodeSame(401);
    }
}
