<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class TransactionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {

        $user = $this->getReference(AppFixtures::USER_REFERENCE, User::class);

        $rentCourse = $this->getReference(CourseFixtures::COURSE_ENGLISH_BASIC, Course::class);

        $buyCourse = $this->getReference(CourseFixtures::COURSE_SQL_POSTGRESQL, Course::class);

        $expiredRentCourse = $this->getReference(CourseFixtures::COURSE_WEB_DESIGN, Course::class);

        // Аренда, которая скоро закончится — нужна для payment:ending:notification
        $endingRent = (new Transaction())
            ->setUser($user)
            ->setCourse($rentCourse)
            ->setType(Transaction::TYPE_PAYMENT)
            ->setAmount(99.90)
            ->setCreatedAt(new \DateTimeImmutable('first day of this month 10:00:00'))
            ->setExpiresAt(new \DateTimeImmutable('tomorrow 10:00:00'));

        // Покупка в текущем месяце — нужна для payment:report
        $buyPayment = (new Transaction())
            ->setUser($user)
            ->setCourse($buyCourse)
            ->setType(Transaction::TYPE_PAYMENT)
            ->setAmount(159.00)
            ->setCreatedAt(new \DateTimeImmutable('first day of this month +1 day 12:00:00'))
            ->setExpiresAt(null);

        // Истекшая аренда — нужна для проверки skip_expired
        $expiredRent = (new Transaction())
            ->setUser($user)
            ->setCourse($expiredRentCourse)
            ->setType(Transaction::TYPE_PAYMENT)
            ->setAmount(129.00)
            ->setCreatedAt(new \DateTimeImmutable('first day of this month +2 days'))
            ->setExpiresAt(new \DateTimeImmutable('-1 day'));

        // Старая покупка — не должна попадать в отчет за текущий месяц
        $oldBuyPayment = (new Transaction())
            ->setUser($user)
            ->setCourse($buyCourse)
            ->setType(Transaction::TYPE_PAYMENT)
            ->setAmount(159.00)
            ->setCreatedAt(new \DateTimeImmutable('-2 months'))
            ->setExpiresAt(null);

        foreach ([$endingRent, $buyPayment, $expiredRent, $oldBuyPayment] as $transaction) {
            $manager->persist($transaction);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            AppFixtures::class,
            CourseFixtures::class,
        ];
    }
}