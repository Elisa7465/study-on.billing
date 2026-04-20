<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserControllerTest extends WebTestCase
{
    public function testCurrentUserSuccess(): void
    {
        $client = static::createClient();
        $token = $this->getToken($client);

        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertSame('user@example.com', $data['username']);
        self::assertArrayHasKey('roles', $data);
        self::assertContains('ROLE_USER', $data['roles']);
        self::assertArrayHasKey('balance', $data);
    }

    public function testCurrentUserWithoutToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/users/current');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCurrentUserWithInvalidToken(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/users/current',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer invalid-token']
        );

        self::assertResponseStatusCodeSame(401);
    }

    private function getToken($client): string
    {
        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);

        return $data['token'];
    }
}