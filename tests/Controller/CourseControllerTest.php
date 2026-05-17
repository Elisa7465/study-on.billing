<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CourseControllerTest extends WebTestCase
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

    // Проверяет, что список курсов доступен без авторизации.
    public function testGetCoursesListSuccess(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/courses');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertGreaterThan(0, count($data));
        self::assertArrayHasKey('code', $data[0]);
        self::assertArrayHasKey('type', $data[0]);
    }

    // Проверяет, что список курсов содержит курсы из фикстур.
    public function testGetCoursesListContainsExpectedCourses(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/courses');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $codes = array_column($data, 'code');

        self::assertContains('html-basic', $codes);
        self::assertContains('symfony-rent', $codes);
        self::assertContains('symfony-buy', $codes);
    }

    // Проверяет, что у бесплатного курса нет цены в ответе.
    public function testFreeCourseHasNoPrice(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/courses');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $freeCourse = null;

        foreach ($data as $course) {
            if ('html-basic' === $course['code']) {
                $freeCourse = $course;
                break;
            }
        }

        self::assertNotNull($freeCourse);
        self::assertSame('free', $freeCourse['type']);
        self::assertArrayNotHasKey('price', $freeCourse);
    }

    // Проверяет, что у платных курсов есть цена.
    public function testPaidCoursesHavePrice(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/courses');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $rentCourse = null;
        $buyCourse = null;

        foreach ($data as $course) {
            if ('symfony-rent' === $course['code']) {
                $rentCourse = $course;
            }

            if ('symfony-buy' === $course['code']) {
                $buyCourse = $course;
            }
        }

        self::assertNotNull($rentCourse);
        self::assertSame('rent', $rentCourse['type']);
        self::assertArrayHasKey('price', $rentCourse);
        self::assertSame('99.90', $rentCourse['price']);

        self::assertNotNull($buyCourse);
        self::assertSame('buy', $buyCourse['type']);
        self::assertArrayHasKey('price', $buyCourse);
        self::assertSame('159.00', $buyCourse['price']);
    }

    // Проверяет получение бесплатного курса по коду.
    public function testGetFreeCourseByCodeSuccess(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/courses/html-basic');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('html-basic', $data['code']);
        self::assertSame('free', $data['type']);
        self::assertArrayNotHasKey('price', $data);
    }

    // Проверяет получение арендного курса по коду.
    public function testGetRentCourseByCodeSuccess(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/courses/symfony-rent');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('symfony-rent', $data['code']);
        self::assertSame('rent', $data['type']);
        self::assertSame('99.90', $data['price']);
    }

    // Проверяет получение курса с полной покупкой по коду.
    public function testGetBuyCourseByCodeSuccess(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/courses/symfony-buy');

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('symfony-buy', $data['code']);
        self::assertSame('buy', $data['type']);
        self::assertSame('159.00', $data['price']);
    }

    // Проверяет ошибку при получении несуществующего курса.
    public function testGetCourseByCodeNotFound(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/courses/unknown-course');

        self::assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $data['code']);
        self::assertSame('Курс не найден', $data['message']);
    }

    // Проверяет, что нельзя оплатить курс без токена.
    public function testPayCourseUnauthorized(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/courses/symfony-buy/pay');

        self::assertResponseStatusCodeSame(401);
    }

    // Проверяет успешную оплату бесплатного курса.
    public function testPayFreeCourseSuccess(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'POST',
            '/api/v1/courses/html-basic/pay',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($data['success']);
        self::assertSame('free', $data['course_type']);
        self::assertArrayHasKey('expires_at', $data);
        self::assertNull($data['expires_at']);
    }

    // Проверяет успешную оплату арендного курса.
    public function testPayRentCourseSuccess(): void
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

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($data['success']);
        self::assertSame('rent', $data['course_type']);
        self::assertArrayHasKey('expires_at', $data);
        self::assertNotNull($data['expires_at']);
    }

    // Проверяет успешную оплату курса с полной покупкой.
    public function testPayBuyCourseSuccess(): void
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

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($data['success']);
        self::assertSame('buy', $data['course_type']);
        self::assertArrayHasKey('expires_at', $data);
        self::assertNull($data['expires_at']);
    }

    // Проверяет ошибку при оплате несуществующего курса.
    public function testPayCourseNotFound(): void
    {
        $auth = $this->authenticateUser();

        $auth['client']->request(
            'POST',
            '/api/v1/courses/unknown-course/pay',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(404);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(404, $data['code']);
        self::assertSame('Курс не найден', $data['message']);
    }

    // Проверяет ошибку при недостатке средств.
    public function testPayCourseWithNotEnoughMoney(): void
    {
        $auth = $this->authenticateUser('poor-user@example.com');

        $auth['client']->request(
            'POST',
            '/api/v1/courses/symfony-buy/pay',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token']]
        );

        self::assertResponseStatusCodeSame(406);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(406, $data['code']);
        self::assertSame('На вашем счету недостаточно средств', $data['message']);
    }

    // Проверяет неуспешную редактирование курса.
    public function testEditCourseDeniedForUser(): void
    {
        $auth = $this->authenticateUser('user@example.com', 'user123');

        $auth['client']->jsonRequest(
            'POST',
            '/api/v1/courses/symfony-rent',
            [
                'type' => 'rent',
                'title' => 'Новое название',
                'code' => 'symfony-rent-new',
                'price' => 120,
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token'],
            ]
        );

        self::assertResponseStatusCodeSame(403);
    }
    // Проверяет успешную редактирование курса.
    public function testEditCourseAllowedForSuperAdmin(): void
    {
        $auth = $this->authenticateUser('admin@example.com', 'admin123');

        $auth['client']->jsonRequest(
            'POST',
            '/api/v1/courses/symfony-rent',
            [
                'type' => 'rent',
                'title' => 'Новое название',
                'code' => 'symfony-rent-new',
                'price' => 120,
            ],
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $auth['token'],
            ]
        );

        self::assertResponseStatusCodeSame(200);

        $data = json_decode($auth['client']->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['success' => true], $data);
    }
}