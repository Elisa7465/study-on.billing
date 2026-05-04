<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function deposit(User $user, float $amount): Transaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма пополнения должна быть больше нуля.');
        }

        return $this->entityManager->wrapInTransaction(function () use ($user, $amount): Transaction {
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setCourse(null);
            $transaction->setType(Transaction::TYPE_DEPOSIT);
            $transaction->setAmount($amount);
            $transaction->setCreatedAt(new \DateTimeImmutable());
            $transaction->setExpiresAt(null);

            $user->setBalance($user->getBalance() + $amount);

            $this->entityManager->persist($transaction);
            $this->entityManager->persist($user);

            return $transaction;
        });
    }

    public function payCourse(User $user, Course $course): Transaction
    {
        $price = $course->getPrice() ?? 0.0;

        if ($price < 0) {
            throw new \InvalidArgumentException('Стоимость курса не может быть отрицательной.');
        }

        if ($user->getBalance() < $price) {
            throw new \RuntimeException('На вашем счету недостаточно средств', 406);
        }

        return $this->entityManager->wrapInTransaction(function () use ($user, $course, $price): Transaction {
            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setCourse($course);
            $transaction->setType(Transaction::TYPE_PAYMENT);
            $transaction->setAmount($price);
            $transaction->setCreatedAt(new \DateTimeImmutable());

            if ($course->isRent()) {
                $transaction->setExpiresAt(new \DateTimeImmutable('+1 week'));
            } else {
                $transaction->setExpiresAt(null);
            }

            if (!$course->isFree()) {
                $user->setBalance($user->getBalance() - $price);
            }

            $this->entityManager->persist($transaction);
            $this->entityManager->persist($user);

            return $transaction;
        });
    }
}