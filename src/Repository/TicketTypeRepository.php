<?php

namespace App\Repository;

use App\Entity\TicketType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; 
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketType>
 *
 * @method TicketType|null find($id, $lockMode = null, $lockVersion = null) 
 * @method TicketType|null findOneBy(array $criteria, array $orderBy = null) 
 * @method TicketType[]    findAll() 
 * @method TicketType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null) 
 */
class TicketTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketType::class);
    }
}