<?php

namespace App\Repository;

use App\Entity\EventTicketType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventTicketType>
 *
 * @method EventTicketType|null find($id, $lockMode = null, $lockVersion = null)
 * @method EventTicketType|null findOneBy(array $criteria, array $orderBy = null)
 * @method EventTicketType[]    findAll()
 * @method EventTicketType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventTicketTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventTicketType::class);
    }
    
    /**
     * Find available ticket types for an event
     */
    public function findAvailableByEvent($event): array
    {
        return $this->createQueryBuilder('tt')
            ->where('tt.event = :event')
            ->andWhere('tt.quantity > tt.sold')
            ->andWhere('tt.endDate IS NULL OR tt.endDate > :now')
            ->setParameter('event', $event)
            ->setParameter('now', new \DateTime())
            ->orderBy('tt.price', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Find early bird ticket types
     */
    public function findEarlyBird(): array
    {
        return $this->createQueryBuilder('tt')
            ->where('tt.earlyBird = :earlyBird')
            ->andWhere('tt.quantity > tt.sold')
            ->andWhere('tt.endDate > :now')
            ->setParameter('earlyBird', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('tt.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}