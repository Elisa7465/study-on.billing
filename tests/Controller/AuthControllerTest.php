<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testAuthSuccess(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);
        self::assertNotEmpty($data['token']);
    }

    public function testAuthWithWrongPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthWithUnknownUser(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'unknown@example.com',
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthWithInvalidJsonFields(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'email' => 'user@example.com',
            'pass' => 'user123',
        ]);

        self::assertTrue(
            in_array($client->getResponse()->getStatusCode(), [400, 401], true)
        );
    }
}