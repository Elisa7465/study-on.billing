<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransactionControllerTest extends WebTestCase
{
    private function authenticateUser(string $username = 'user@example.com', string $password = 'user123'): array
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/api/v1/auth', [
            'username' => $username,
            'password' => $password,
        ]);

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'client' => $client,
            'token' => $data['token'],
        ];
    }

    // Проверяет, что история транзакций недоступна без токена.
    public function testGetTransactionsUnauthorized(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/transactions');

        self::assertResponseStatusCodeSame(401);
    }

    // Проверяет, что авторизованный пользователь может получить историю транзакций.
    public function testGetTransactionsSuccess(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'GET',
            '/api/v1/transactions',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertGreaterThan(0, count($data));
    }

    // Проверяет структуру объекта транзакции.
    public function testGetTransactionsStructure(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'GET',
            '/api/v1/transactions',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $transaction = $data[0];

        self::assertArrayHasKey('id', $transaction);
        self::assertArrayHasKey('created_at', $transaction);
        self::assertArrayHasKey('type', $transaction);
        self::assertArrayHasKey('amount', $transaction);
    }

    // Проверяет, что в истории есть начальное пополнение баланса.
    public function testTransactionsContainInitialDeposit(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'GET',
            '/api/v1/transactions',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $deposits = array_filter(
            $data,
            static fn (array $transaction): bool => 'deposit' === $transaction['type']
        );

        self::assertGreaterThan(0, count($deposits));
    }

    // Проверяет фильтр транзакций по начислениям.
    public function testGetTransactionsFilterByDepositType(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'GET',
            '/api/v1/transactions?filter[type]=deposit',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertGreaterThan(0, count($data));

        foreach ($data as $transaction) {
            self::assertSame('deposit', $transaction['type']);
            self::assertArrayNotHasKey('course_code', $transaction);
        }
    }

    // Проверяет фильтр транзакций по списаниям.
    public function testGetTransactionsFilterByPaymentType(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'POST',
            '/api/v1/courses/symfony-buy/pay',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $auth['client']->request(
            'GET',
            '/api/v1/transactions?filter[type]=payment',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertGreaterThan(0, count($data));

        foreach ($data as $transaction) {
            self::assertSame('payment', $transaction['type']);
        }
    }

    // Проверяет фильтр транзакций по коду курса.
    public function testGetTransactionsFilterByCourseCode(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'POST',
            '/api/v1/courses/symfony-rent/pay',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $auth['client']->request(
            'GET',
            '/api/v1/transactions?filter[course_code]=symfony-rent',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertGreaterThan(0, count($data));

        foreach ($data as $transaction) {
            self::assertArrayHasKey('course_code', $transaction);
            self::assertSame('symfony-rent', $transaction['course_code']);
        }
    }

    // Проверяет, что фильтр по несуществующему курсу возвращает пустой список.
    public function testGetTransactionsFilterByUnknownCourseCode(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'GET',
            '/api/v1/transactions?filter[course_code]=unknown-course',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([], $data);
    }

    // Проверяет фильтр, который скрывает истекшие аренды.
    public function testGetTransactionsFilterSkipExpired(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'GET',
            '/api/v1/transactions?filter[skip_expired]=1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data as $transaction) {
            if (!isset($transaction['expires_at'])) {
                continue;
            }

            self::assertGreaterThan(
                time(),
                strtotime($transaction['expires_at'])
            );
        }
    }

    // Проверяет одновременную работу фильтров type и course_code.
    public function testGetTransactionsMultipleFilters(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'POST',
            '/api/v1/courses/symfony-rent/pay',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $auth['client']->request(
            'GET',
            '/api/v1/transactions?filter[type]=payment&filter[course_code]=symfony-rent',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertGreaterThan(0, count($data));

        foreach ($data as $transaction) {
            self::assertSame('payment', $transaction['type']);
            self::assertSame('symfony-rent', $transaction['course_code']);
        }
    }

    // Проверяет, что у списания за арендный курс есть срок действия.
    public function testRentCoursePaymentHasExpiresAt(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'POST',
            '/api/v1/courses/symfony-rent/pay',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $auth['client']->request(
            'GET',
            '/api/v1/transactions?filter[type]=payment&filter[course_code]=symfony-rent',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertGreaterThan(0, count($data));
        self::assertArrayHasKey('expires_at', $data[0]);
        self::assertNotNull($data[0]['expires_at']);
    }
}