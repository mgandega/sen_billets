<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 *
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findPublishedEvents(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', 'published')
            ->andWhere('e.eventDate >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.eventDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.category = :category')
            ->andWhere('e.status = :status')
            ->setParameter('category', $category)
            ->setParameter('status', 'published')
            ->andWhere('e.eventDate >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.eventDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchEvents(string $query): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.title LIKE :query OR e.description LIKE :query OR e.venue LIKE :query')
            ->andWhere('e.status = :status')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('status', 'published')
            ->andWhere('e.eventDate >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.eventDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByOrganizer(User $organizer): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.organizer = :organizer')
            ->setParameter('organizer', $organizer)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function findFeaturedEvents(int $limit = 3): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', 'published')
            ->andWhere('e.eventDate >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.soldTickets', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    public function findTree(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', 'published')
            ->andWhere('e.eventDate >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.soldTickets', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }

    public function findUpcomingEvents(int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', 'published')
            ->andWhere('e.eventDate >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.eventDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPopularCategories(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.category, COUNT(e.id) as eventCount')
            ->andWhere('e.status = :status')
            ->setParameter('status', 'published')
            ->groupBy('e.category')
            ->orderBy('eventCount', 'DESC')
            ->getQuery()
            ->getResult();
    }
}