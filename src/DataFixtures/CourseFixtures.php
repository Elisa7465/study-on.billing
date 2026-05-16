<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class CourseFixtures extends Fixture
{
    public const COURSE_PHP_BASIC = 'course_php_basic';
    public const COURSE_SYMFONY_START = 'course_symfony_start';
    public const COURSE_SQL_POSTGRESQL = 'course_sql_postgresql';
    public const COURSE_WEB_DESIGN = 'course_web_design';
    public const COURSE_PERSONAL_FINANCE = 'course_personal_finance';
    public const COURSE_TIME_MANAGEMENT = 'course_time_management';
    public const COURSE_ENGLISH_BASIC = 'course_english_basic';

    public function load(ObjectManager $manager): void
    {
        $coursesData = [
            [
                'reference' => 'legacy_free_course',
                'symbolCode' => 'html-basic',
                'title' => 'Основы HTML',
                'type' => Course::TYPE_FREE,
                'price' => null,
            ],
            [
                'reference' => 'legacy_rent_course',
                'symbolCode' => 'symfony-rent',
                'title' => 'Symfony в аренду',
                'type' => Course::TYPE_RENT,
                'price' => 99.90,
            ],
            [
                'reference' => 'legacy_buy_course',
                'symbolCode' => 'symfony-buy',
                'title' => 'Symfony навсегда',
                'type' => Course::TYPE_BUY,
                'price' => 159.00,
            ],
            [
                'reference' => self::COURSE_PHP_BASIC,
                'symbolCode' => 'php-basic',
                'title' => 'Основы PHP',
                'type' => Course::TYPE_FREE,
                'price' => null,
            ],
            [
                'reference' => self::COURSE_SYMFONY_START,
                'symbolCode' => 'symfony-start',
                'title' => 'Введение в Symfony',
                'type' => Course::TYPE_RENT,
                'price' => 99.90,
            ],
            [
                'reference' => self::COURSE_SQL_POSTGRESQL,
                'symbolCode' => 'sql-postgresql',
                'title' => 'Работа с PostgreSQL',
                'type' => Course::TYPE_BUY,
                'price' => 159.00,
            ],
            [
                'reference' => self::COURSE_WEB_DESIGN,
                'symbolCode' => 'web-design-basic',
                'title' => 'Основы веб-дизайна',
                'type' => Course::TYPE_RENT,
                'price' => 129.00,
            ],
            [
                'reference' => self::COURSE_PERSONAL_FINANCE,
                'symbolCode' => 'personal-finance',
                'title' => 'Личные финансы и бюджет',
                'type' => Course::TYPE_FREE,
                'price' => null,
            ],
            [
                'reference' => self::COURSE_TIME_MANAGEMENT,
                'symbolCode' => 'time-management',
                'title' => 'Тайм-менеджмент',
                'type' => Course::TYPE_BUY,
                'price' => 199.00,
            ],
            [
                'reference' => self::COURSE_ENGLISH_BASIC,
                'symbolCode' => 'english-basic',
                'title' => 'Английский для начинающих',
                'type' => Course::TYPE_RENT,
                'price' => 79.90,
            ],
        ];

        foreach ($coursesData as $courseData) {
            $course = new Course();
            $course->setSymbolCode($courseData['symbolCode']);
            $course->setTitle($courseData['title']);
            $course->setType($courseData['type']);
            $course->setPrice($courseData['price']);

            $manager->persist($course);
            $this->addReference($courseData['reference'], $course);
        }

        $manager->flush();
    }
}