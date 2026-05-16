<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegisterControllerTest extends WebTestCase
{
    // Проверяет успешную регистрацию пользователя.
    public function testRegisterSuccess(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'new-user-'.uniqid('', true).'@example.com',
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);

        self::assertArrayHasKey('token', $data);
        self::assertNotEmpty($data['token']);

        self::assertArrayHasKey('refresh_token', $data);
        self::assertNotEmpty($data['refresh_token']);

        self::assertArrayHasKey('roles', $data);
        self::assertContains('ROLE_USER', $data['roles']);
    }

    // Проверяет ошибку регистрации с некорректным email.
    public function testRegisterWithInvalidEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'invalid-email',
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('email', $data['errors']);
    }

    // Проверяет ошибку регистрации с коротким паролем.
    public function testRegisterWithShortPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'short-password-'.uniqid('', true).'@example.com',
            'password' => '123',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('password', $data['errors']);
    }

    // Проверяет ошибку регистрации без email.
    public function testRegisterWithoutEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('email', $data['errors']);
    }

    // Проверяет ошибку регистрации без password.
    public function testRegisterWithoutPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'without-password-'.uniqid('', true).'@example.com',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('password', $data['errors']);
    }

    // Проверяет ошибку регистрации пользователя с уже существующим email.
    public function testRegisterWithExistingEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'user@example.com',
            'password' => 'user123',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('email', $data['errors']);
    }
}