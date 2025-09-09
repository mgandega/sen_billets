<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\CartItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    public function findByUserAndEvent(User $user, Event $event): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.ticketType', 't')
            ->andWhere('c.user = :user')
            ->andWhere('t.event = :event')
            ->setParameter('user', $user)
            ->setParameter('event', $event)
            ->getQuery()
            ->getResult();
    }

    public function getTotalByUser(User $user): float
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.quantity * t.price) as total')
            ->join('c.ticketType', 't')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getItemsCountByUser(User $user): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('SUM(c.quantity) as count')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}