<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthControllerTest extends WebTestCase
{
    // Проверяет успешную авторизацию пользователя.
    public function testAuthSuccess(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);
        self::assertNotEmpty($data['token']);

        self::assertArrayHasKey('refresh_token', $data);
        self::assertNotEmpty($data['refresh_token']);
    }

    // Проверяет ошибку авторизации с неверным паролем.
    public function testAuthWithInvalidPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('code', $data);
        self::assertArrayHasKey('message', $data);
    }

    // Проверяет ошибку авторизации с несуществующим пользователем.
    public function testAuthWithUnknownUser(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'unknown@example.com',
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('code', $data);
        self::assertArrayHasKey('message', $data);
    }

    // Проверяет ошибку авторизации без username.
    public function testAuthWithoutUsername(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('code', $data);
        self::assertArrayHasKey('message', $data);
    }

    // Проверяет ошибку авторизации без password.
    public function testAuthWithoutPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => 'user@example.com',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('code', $data);
        self::assertArrayHasKey('message', $data);
    }
}