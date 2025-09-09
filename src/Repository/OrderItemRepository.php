<?php

namespace App\Repository;

use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    public function findByOrder($order): array
    {
        return $this->createQueryBuilder('oi')
            ->andWhere('oi.orderRef = :order')
            ->setParameter('order', $order)
            ->getQuery()
            ->getResult();
    }
}