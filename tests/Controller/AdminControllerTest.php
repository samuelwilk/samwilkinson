<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    public function testAdminDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        // Should redirect to login page
        $this->assertResponseRedirects();
    }

    public function testProjectCrudRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin?crudAction=index&crudControllerFqcn=App%5CController%5CAdmin%5CProjectCrudController');

        $this->assertResponseRedirects();
    }

    public function testPostCrudRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin?crudAction=index&crudControllerFqcn=App%5CController%5CAdmin%5CPostCrudController');

        $this->assertResponseRedirects();
    }

    public function testCollectionCrudRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin?crudAction=index&crudControllerFqcn=App%5CController%5CAdmin%5CCollectionCrudController');

        $this->assertResponseRedirects();
    }

    public function testPhotoCrudRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin?crudAction=index&crudControllerFqcn=App%5CController%5CAdmin%5CPhotoCrudController');

        $this->assertResponseRedirects();
    }
}
