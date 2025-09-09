<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findByUser($user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingPayments(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->andWhere('p.createdAt < :timeout')
            ->setParameter('timeout', new \DateTime('-30 minutes'))
            ->getQuery()
            ->getResult();
    }

    public function getTotalRevenue(\DateTime $from = null, \DateTime $to = null): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->andWhere('p.status = :status')
            ->setParameter('status', Payment::STATUS_COMPLETED);

        if ($from) {
            $qb->andWhere('p.completedAt >= :from')
               ->setParameter('from', $from);
        }

        if ($to) {
            $qb->andWhere('p.completedAt <= :to')
               ->setParameter('to', $to);
        }

        return (float) ($qb->getQuery()->getSingleScalarResult() ?? 0);
    }

    public function getPaymentsByMethod(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.method, COUNT(p.id) as count, SUM(p.amount) as total')
            ->andWhere('p.status = :status')
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->groupBy('p.method')
            ->getQuery()
            ->getResult();
    }

    public function getRecentPayments(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}