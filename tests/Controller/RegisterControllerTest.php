<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegisterControllerTest extends WebTestCase
{
    public function testRegisterSuccess(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'newuser@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('roles', $data);
        self::assertContains('ROLE_USER', $data['roles']);
    }

    public function testRegisterWithInvalidEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'not-an-email',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('email', $data['errors']);
    }

    public function testRegisterWithShortPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'shortpass@example.com',
            'password' => '123',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('password', $data['errors']);
    }

    public function testRegisterWithExistingEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
        self::assertArrayHasKey('email', $data['errors']);
    }

    public function testRegisterWithEmptyData(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/register', [
            'email' => '',
            'password' => '',
        ]);

        self::assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('errors', $data);
    }
}