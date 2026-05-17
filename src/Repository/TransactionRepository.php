<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Course;
use App\Entity\User;


/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function findByUserWithFilters(
        User $user,
        ?int $type = null,
        ?Course $course = null,
        bool $skipExpired = false,
    ): array {
        $queryBuilder = $this->createQueryBuilder('transaction')
            ->andWhere('transaction.user = :user')
            ->setParameter('user', $user)
            ->orderBy('transaction.createdAt', 'DESC');

        if (null !== $type) {
            $queryBuilder
                ->andWhere('transaction.type = :type')
                ->setParameter('type', $type);
        }

        if (null !== $course) {
            $queryBuilder
                ->andWhere('transaction.course = :course')
                ->setParameter('course', $course);
        }

        if ($skipExpired) {
            $queryBuilder
                ->andWhere('transaction.expiresAt IS NULL OR transaction.expiresAt > :now')
                ->setParameter('now', new \DateTimeImmutable());
        }

        return $queryBuilder->getQuery()->getResult();
    }


    public function findEndingRentTransactions(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('t')
            ->join('t.course', 'c')
            ->join('t.user', 'u')
            ->andWhere('t.type = :type')
            ->andWhere('c.type = :courseType')
            ->andWhere('t.expiresAt BETWEEN :from AND :to')
            ->setParameter('type', Transaction::TYPE_PAYMENT)
            ->setParameter('courseType', Course::TYPE_RENT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('t.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }


    public function getMonthlyPaidCoursesReport(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('t')
            ->select('
                c.title as courseName,
                c.type as courseType,
                COUNT(t.id) as paymentsCount,
                SUM(t.amount) as totalAmount
            ')
            ->join('t.course', 'c')
            ->andWhere('t.type = :type')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->setParameter('type', Transaction::TYPE_PAYMENT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('c.id')
            ->addGroupBy('c.symbolCode')
            ->addGroupBy('c.type')
            ->orderBy('c.symbolCode', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

//    /**
//     * @return Transaction[] Returns an array of Transaction objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Transaction
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
